<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\Api\V1\ApplicationDatabaseService;
use App\Services\ProfileMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    /**
     * Number of profiles shown in each Home section.
     */
    private const HOME_LIMIT = 10;

    /**
     * Home page data.
     */
    public function index(
        Request $request,
        ProfileMatchingService $matchingService,
        ApplicationDatabaseService $databaseService
    ): JsonResponse {

        /** @var Member $loggedInMember */
        $loggedInMember = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Application database connection
        |--------------------------------------------------------------------------
        |
        | ResolveApplication middleware has already connected the current
        | application database.
        |
        */

        $applicationDb = $databaseService->connection();

        /*
        |--------------------------------------------------------------------------
        | Determine opposite gender
        |--------------------------------------------------------------------------
        */

        $gender = strtolower(
            trim($loggedInMember->gender ?? '')
        );

        if ($gender === 'male') {

            $oppositeGender = 'female';
        } elseif ($gender === 'female') {

            $oppositeGender = 'male';
        } else {

            return response()->json([
                'success' => false,
                'message' => 'Unable to determine your gender.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Common profile query
        |--------------------------------------------------------------------------
        */

        $baseQuery = function () use (
            $loggedInMember,
            $oppositeGender
        ) {

            return Member::query()

                /*
                |--------------------------------------------------------------------------
                | Don't show logged-in member
                |--------------------------------------------------------------------------
                */

                ->where(
                    'id',
                    '!=',
                    $loggedInMember->id
                )

                /*
                |--------------------------------------------------------------------------
                | Opposite gender
                |--------------------------------------------------------------------------
                */

                ->whereRaw(
                    'LOWER(TRIM(gender)) = ?',
                    [$oppositeGender]
                )

                /*
                |--------------------------------------------------------------------------
                | Active profiles only
                |--------------------------------------------------------------------------
                */

                ->whereRaw(
                    "LOWER(TRIM(active)) = 'yes'"
                )

                /*
                |--------------------------------------------------------------------------
                | Hidden profiles
                |--------------------------------------------------------------------------
                */

                ->where(function ($query) {

                    $query
                        ->whereNull('profile_hide')
                        ->orWhere('profile_hide', '')
                        ->orWhereRaw(
                            "LOWER(TRIM(profile_hide)) = 'no'"
                        );
                });
        };

        /*
        |--------------------------------------------------------------------------
        | Fields returned to Home
        |--------------------------------------------------------------------------
        */

        $fields = [
            'id',
            'profile_id',
            'full_name',
            'birth_date_time',
            'gender',
            'height',
            'religion',
            'mother_tongue',
            'cast',
            'marital_status',
            'education',
            'occupation',
            'annual_income',
            'country_living_in',
            'state_living_in',
            'city_living_in',
            'photo',
            'profile_completed',
            'member_type',
            'registration_date',
            'promoted',
        ];

        /*
        |--------------------------------------------------------------------------
        | 1. VERIFIED PROFILES
        |--------------------------------------------------------------------------
        */

        $verifiedProfiles = $baseQuery()

            ->whereRaw(
                "LOWER(TRIM(member_type)) = 'verified'"
            )

            ->select($fields)

            ->orderByDesc('promoted')
            ->orderByDesc('id')

            ->limit(self::HOME_LIMIT)

            ->get()

            ->map(function ($member) {

                return $this->formatMember($member);
            })

            ->values();

        /*
        |--------------------------------------------------------------------------
        | 2. SHORTLISTED PROFILES
        |--------------------------------------------------------------------------
        |
        | short_listed.profile_id contains members.id.
        |
        */

        $shortlistedProfileIds = $applicationDb

            ->table('short_listed')

            ->where(
                'member_id',
                $loggedInMember->id
            )

            ->orderByDesc('created_at')

            ->limit(self::HOME_LIMIT)

            ->pluck('profile_id');

        $shortlistedProfiles = collect();

        if ($shortlistedProfileIds->isNotEmpty()) {

            /*
            |--------------------------------------------------------------------------
            | Fetch actual member records
            |--------------------------------------------------------------------------
            */

            $shortlistedMembers = $baseQuery()

                ->whereIn(
                    'id',
                    $shortlistedProfileIds
                )

                ->select($fields)

                ->get()

                ->keyBy('id');

            /*
            |--------------------------------------------------------------------------
            | Preserve shortlist order
            |--------------------------------------------------------------------------
            */

            $shortlistedProfiles = $shortlistedProfileIds

                ->map(function ($profileId) use (
                    $shortlistedMembers
                ) {

                    $member =
                        $shortlistedMembers->get($profileId);

                    if (!$member) {
                        return null;
                    }

                    return $this->formatMember($member);
                })

                ->filter()

                ->values();
        }

        /*
        |--------------------------------------------------------------------------
        | 3. RECENT PROFILES
        |--------------------------------------------------------------------------
        */

        $recentProfiles = $baseQuery()

            ->select($fields)

            ->orderByDesc('registration_date')

            ->limit(self::HOME_LIMIT)

            ->get()

            ->map(function ($member) {

                return $this->formatMember($member);
            })

            ->values();

        /*
        |--------------------------------------------------------------------------
        | 4. MATCHING PROFILES
        |--------------------------------------------------------------------------
        */

        $matchingProfiles = $matchingService->getMatches(
            $loggedInMember,
            self::HOME_LIMIT
        );

        /*
        |--------------------------------------------------------------------------
        | 5. WHO VIEWED MY PROFILE
        |--------------------------------------------------------------------------
        */

        $profileViewers = $this->getProfileViewers(
            $loggedInMember,
            $applicationDb,
            $baseQuery,
            $fields
        );

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'message' =>
            'Home data fetched successfully.',

            'data' => [

                'verified_profiles' =>
                $verifiedProfiles,

                'shortlisted_profiles' =>
                $shortlistedProfiles,

                'recent_profiles' =>
                $recentProfiles,

                'matching_profiles' =>
                $matchingProfiles,

                'profile_viewers' =>
                $profileViewers,
            ],
        ]);
    }

    /**
     * Get members who viewed the logged-in member's profile.
     */
    private function getProfileViewers(
        Member $loggedInMember,
        $applicationDb,
        callable $baseQuery,
        array $fields
    ) {

        /*
        |--------------------------------------------------------------------------
        | Get latest view for every unique viewer
        |--------------------------------------------------------------------------
        */

        $latestViews = $applicationDb

            ->table('profile_viewed')

            ->where(
                'viewed_profile_id',
                $loggedInMember->id
            )

            ->whereIn('id', function ($query) use (
                $loggedInMember
            ) {

                $query
                    ->selectRaw('MAX(id)')
                    ->from('profile_viewed')
                    ->where(
                        'viewed_profile_id',
                        $loggedInMember->id
                    )
                    ->groupBy('member_id');
            })

            ->orderByDesc('created_at')

            ->limit(self::HOME_LIMIT)

            ->get();

        /*
        |--------------------------------------------------------------------------
        | No viewers
        |--------------------------------------------------------------------------
        */

        if ($latestViews->isEmpty()) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Viewer member IDs
        |--------------------------------------------------------------------------
        */

        $viewerIds = $latestViews

            ->pluck('member_id')

            ->unique()

            ->values();

        /*
        |--------------------------------------------------------------------------
        | Fetch viewer profiles
        |--------------------------------------------------------------------------
        */

        $viewers = $baseQuery()

            ->whereIn(
                'id',
                $viewerIds
            )

            ->select($fields)

            ->get()

            ->keyBy('id');

        /*
        |--------------------------------------------------------------------------
        | Preserve latest-view order
        |--------------------------------------------------------------------------
        */

        return $latestViews

            ->map(function ($view) use ($viewers) {

                $viewer =
                    $viewers->get($view->member_id);

                /*
                |--------------------------------------------------------------------------
                | Viewer may no longer be visible
                |--------------------------------------------------------------------------
                */

                if (!$viewer) {
                    return null;
                }

                $data =
                    $this->formatMember($viewer);

                /*
                |--------------------------------------------------------------------------
                | Add view timestamp
                |--------------------------------------------------------------------------
                */

                $data['viewed_at'] =
                    $view->created_at;

                return $data;
            })

            ->filter()

            ->values();
    }

    /**
     * Format member for Home page.
     */
    private function formatMember(
        Member $member
    ): array {

        $age = null;

        if (!empty($member->birth_date_time)) {

            try {

                $age = Carbon::parse(
                    $member->birth_date_time
                )->age;
            } catch (\Throwable $e) {

                $age = null;
            }
        }

        return [

            'id' =>
            $member->id,

            'profile_id' =>
            $member->profile_id,

            'full_name' =>
            $member->full_name,

            'age' =>
            $age,

            'gender' =>
            $member->gender,

            'height' =>
            $member->height,

            'religion' =>
            $member->religion,

            'mother_tongue' =>
            $member->mother_tongue,

            'cast' =>
            $member->cast,

            'marital_status' =>
            $member->marital_status,

            'education' =>
            $member->education,

            'occupation' =>
            $member->occupation,

            'annual_income' =>
            $member->annual_income,

            'country_living_in' =>
            $member->country_living_in,

            'state_living_in' =>
            $member->state_living_in,

            'city_living_in' =>
            $member->city_living_in,

            'photo' =>
            $member->photo,

            'profile_completed' =>
            (int) $member->profile_completed,

            'member_type' =>
            $member->member_type,
        ];
    }
}
