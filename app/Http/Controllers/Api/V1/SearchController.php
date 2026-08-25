<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SearchController extends Controller
{
    /**
     * Quick member search.
     *
     * At least one search parameter is required.
     */
    public function quickSearch(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Validate search filters
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'age_from' => [
                'sometimes',
                'nullable',
                'integer',
                'min:18',
                'max:100',
            ],

            'age_to' => [
                'sometimes',
                'nullable',
                'integer',
                'min:18',
                'max:100',
                'gte:age_from',
            ],

            'religion' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'cast' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'marital_status' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | At least one filter is required
        |--------------------------------------------------------------------------
        */

        $hasFilter = collect([
            $validated['age_from'] ?? null,
            $validated['age_to'] ?? null,
            $validated['religion'] ?? null,
            $validated['cast'] ?? null,
            $validated['marital_status'] ?? null,
        ])->contains(function ($value) {
            return $value !== null && $value !== '';
        });

        if (!$hasFilter) {

            return response()->json([
                'success' => false,
                'message' => 'Please provide at least one search filter.',
                'errors' => [
                    'search' => [
                        'At least one of age range, religion, cast or marital status is required.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Logged-in member
        |--------------------------------------------------------------------------
        */

        /** @var \App\Models\Member $loggedInMember */
        $loggedInMember = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Determine opposite gender
        |--------------------------------------------------------------------------
        */

        $loggedInGender = strtolower(
            trim((string) $loggedInMember->gender)
        );

        if ($loggedInGender === 'male') {

            $oppositeGender = 'female';
        } elseif ($loggedInGender === 'female') {

            $oppositeGender = 'male';
        } else {

            return response()->json([
                'success' => false,
                'message' => 'Unable to determine your gender for this search.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Base query
        |--------------------------------------------------------------------------
        */

        $query = Member::query()

            // Never return logged-in member
            ->where('id', '!=', $loggedInMember->id)

            // Opposite gender only
            ->whereRaw(
                'LOWER(TRIM(gender)) = ?',
                [$oppositeGender]
            );

        /*
        |--------------------------------------------------------------------------
        | Active profiles only
        |--------------------------------------------------------------------------
        */

        $query->whereRaw(
            "LOWER(TRIM(active)) = 'yes'"
        );

        /*
        |--------------------------------------------------------------------------
        | Hidden profiles should not appear
        |--------------------------------------------------------------------------
        */

        $query->where(function ($q) {

            $q->whereNull('profile_hide')
                ->orWhere('profile_hide', '')
                ->orWhereRaw(
                    "LOWER(TRIM(profile_hide)) = 'no'"
                );
        });

        /*
        |--------------------------------------------------------------------------
        | Religion
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['religion'])) {

            $query->where(
                'religion',
                $validated['religion']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Cast
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['cast'])) {

            $query->where(
                'cast',
                $validated['cast']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Marital Status
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['marital_status'])) {

            $query->where(
                'marital_status',
                $validated['marital_status']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Age filter
        |--------------------------------------------------------------------------
        |
        | birth_date_time is stored as:
        |
        | YYYY-MM-DD HH:MM:SS
        |
        | Example:
        | 1990-08-24 08:35:00
        |
        */

        if (
            array_key_exists('age_from', $validated)
            || array_key_exists('age_to', $validated)
        ) {

            /*
            |--------------------------------------------------------------------------
            | Defaults
            |--------------------------------------------------------------------------
            */

            $ageFrom = $validated['age_from'] ?? 18;
            $ageTo   = $validated['age_to'] ?? 100;

            $today = Carbon::today();

            /*
            |--------------------------------------------------------------------------
            | Convert age range into DOB range
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | age_from = 25
            | age_to   = 35
            |
            | DOB:
            |
            | 35 years ago -> oldest
            | 25 years ago -> youngest
            |
            */

            $dobFrom = $today
                ->copy()
                ->subYears($ageTo + 1)
                ->addDay();

            $dobTo = $today
                ->copy()
                ->subYears($ageFrom);

            $query->whereRaw(
                "STR_TO_DATE(
                    birth_date_time,
                    '%Y-%m-%d %H:%i:%s'
                ) BETWEEN ? AND ?",
                [
                    $dobFrom->format('Y-m-d H:i:s'),
                    $dobTo->format('Y-m-d H:i:s'),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Select search result fields
        |--------------------------------------------------------------------------
        */

        $members = $query
            ->select([
                'id',
                'profile_id',
                'full_name',
                'birth_date_time',
                'gender',
                'height',
                'religion',
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
            ])
            ->orderByDesc('promoted')
            ->orderByDesc('id')
            ->paginate(20);

        /*
        |--------------------------------------------------------------------------
        | Format search results
        |--------------------------------------------------------------------------
        */

        $members->getCollection()->transform(
            function ($member) {

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
                    'id' => $member->id,
                    'profile_id' => $member->profile_id,
                    'full_name' => $member->full_name,
                    'age' => $age,
                    'gender' => $member->gender,
                    'height' => $member->height,

                    'religion' => $member->religion,
                    'cast' => $member->cast,
                    'marital_status' => $member->marital_status,

                    'education' => $member->education,
                    'occupation' => $member->occupation,
                    'annual_income' => $member->annual_income,

                    'country_living_in' =>
                    $member->country_living_in,

                    'state_living_in' =>
                    $member->state_living_in,

                    'city_living_in' =>
                    $member->city_living_in,

                    'photo' => $member->photo,

                    'profile_completed' =>
                    (int) $member->profile_completed,
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'message' => 'Search results fetched successfully.',

            'data' => [

                'members' => $members->items(),

                'pagination' => [
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                    'per_page' => $members->perPage(),
                    'total' => $members->total(),
                ],
            ],
        ]);
    }


    /**
     * Search member by profile ID.
     */
    public function searchByProfileId(
        Request $request,
        string $profileId
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | Clean profile ID
        |--------------------------------------------------------------------------
        */

        $profileId = trim($profileId);

        if ($profileId === '') {

            return response()->json([
                'success' => false,
                'message' => 'Profile ID is required.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Logged-in member
        |--------------------------------------------------------------------------
        */

        /** @var \App\Models\Member $loggedInMember */
        $loggedInMember = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Determine opposite gender
        |--------------------------------------------------------------------------
        */

        $loggedInGender = strtolower(
            trim((string) $loggedInMember->gender)
        );

        if ($loggedInGender === 'male') {

            $oppositeGender = 'female';
        } elseif ($loggedInGender === 'female') {

            $oppositeGender = 'male';
        } else {

            return response()->json([
                'success' => false,
                'message' => 'Unable to determine your gender for this search.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Find profile
        |--------------------------------------------------------------------------
        |
        | Only an active, visible profile of the opposite gender
        | can be returned.
        |
        */

        $member = Member::query()

            ->where('profile_id', $profileId)

            ->whereRaw(
                'LOWER(TRIM(gender)) = ?',
                [$oppositeGender]
            )

            ->whereRaw(
                "LOWER(TRIM(active)) = 'yes'"
            )

            ->where(function ($q) {

                $q->whereNull('profile_hide')
                    ->orWhere('profile_hide', '')
                    ->orWhereRaw(
                        "LOWER(TRIM(profile_hide)) = 'no'"
                    );
            })

            ->first();

        /*
        |--------------------------------------------------------------------------
        | Profile not found
        |--------------------------------------------------------------------------
        */

        if (!$member) {

            return response()->json([
                'success' => false,
                'message' => 'Profile not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'message' => 'Profile found successfully.',

            'data' => [

                'member' => [

                    'id' => $member->id,

                    'profile_id' =>
                    $member->profile_id,

                    'full_name' =>
                    $member->full_name,

                    'gender' =>
                    $member->gender,

                    'birth_date_time' =>
                    $member->birth_date_time,

                    'height' =>
                    $member->height,

                    'religion' =>
                    $member->religion,

                    'mother_tongue' =>
                    $member->mother_tongue,

                    'cast' =>
                    $member->cast,

                    'sub_cast' =>
                    $member->sub_cast,

                    'marital_status' =>
                    $member->marital_status,

                    'education' =>
                    $member->education,

                    'occupation' =>
                    $member->occupation,

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
                ],
            ],
        ]);
    }

    /**
     * Advanced member search.
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        /*
    |--------------------------------------------------------------------------
    | Validate filters
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([

            // Age
            'age_from' => [
                'sometimes',
                'nullable',
                'integer',
                'min:18',
                'max:100',
            ],

            'age_to' => [
                'sometimes',
                'nullable',
                'integer',
                'min:18',
                'max:100',
                'gte:age_from',
            ],

            // Religion & Community
            'religion' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'mother_tongue' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'cast' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'sub_cast' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'gotra' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'manglik' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            // Marital
            'marital_status' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            // Personal
            'height_from' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:3',
                'max:8',
            ],

            'height_to' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:3',
                'max:8',
                'gte:height_from',
            ],

            'blood_group' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'no_of_child' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],

            // Education & Career
            'education' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'employed_in' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'occupation' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'designation' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'annual_income' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            // Location
            'country_living_in' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'state_living_in' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'city_living_in' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'native_place' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            // Lifestyle
            'diet' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'is_drinking' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'is_smoking' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'any_disability' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            // Horoscope
            'horoscope_needed' => [
                'sometimes',
                'nullable',
                'in:Yes,No',
            ],
        ]);

    /*
    |--------------------------------------------------------------------------
    | Logged-in member
    |--------------------------------------------------------------------------
    */

        /** @var \App\Models\Member $loggedInMember */
        $loggedInMember = $request->user();

        /*
    |--------------------------------------------------------------------------
    | Determine opposite gender
    |--------------------------------------------------------------------------
    */

        $loggedInGender = strtolower(
            trim((string) $loggedInMember->gender)
        );

        if ($loggedInGender === 'male') {

            $oppositeGender = 'female';
        } elseif ($loggedInGender === 'female') {

            $oppositeGender = 'male';
        } else {

            return response()->json([
                'success' => false,
                'message' => 'Unable to determine your gender for this search.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Base query
    |--------------------------------------------------------------------------
    */

        $query = Member::query()

            ->where('id', '!=', $loggedInMember->id)

            // Opposite gender only
            ->whereRaw(
                'LOWER(TRIM(gender)) = ?',
                [$oppositeGender]
            )

            // Active profiles only
            ->whereRaw(
                "LOWER(TRIM(active)) = 'yes'"
            )

            // Visible profiles only
            ->where(function ($q) {

                $q->whereNull('profile_hide')
                    ->orWhere('profile_hide', '')
                    ->orWhereRaw(
                        "LOWER(TRIM(profile_hide)) = 'no'"
                    );
            });

        /*
    |--------------------------------------------------------------------------
    | Age
    |--------------------------------------------------------------------------
    */

        if (
            array_key_exists('age_from', $validated)
            || array_key_exists('age_to', $validated)
        ) {

            $ageFrom = $validated['age_from'] ?? 18;
            $ageTo   = $validated['age_to'] ?? 100;

            $today = Carbon::today();

            $dobFrom = $today
                ->copy()
                ->subYears($ageTo + 1)
                ->addDay();

            $dobTo = $today
                ->copy()
                ->subYears($ageFrom);

            $query->whereRaw(
                "STR_TO_DATE(
                birth_date_time,
                '%Y-%m-%d %H:%i:%s'
            ) BETWEEN ? AND ?",
                [
                    $dobFrom->format('Y-m-d H:i:s'),
                    $dobTo->format('Y-m-d H:i:s'),
                ]
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Exact-match filters
    |--------------------------------------------------------------------------
    */

        $exactFilters = [
            'religion',
            'mother_tongue',
            'cast',
            'sub_cast',
            'gotra',
            'manglik',
            'marital_status',
            'blood_group',
            'no_of_child',
            'employed_in',
            'annual_income',
            'country_living_in',
            'state_living_in',
            'city_living_in',
            'diet',
            'is_drinking',
            'is_smoking',
            'horoscope_needed',
        ];

        foreach ($exactFilters as $field) {

            if (
                array_key_exists($field, $validated)
                && $validated[$field] !== null
                && $validated[$field] !== ''
            ) {

                $query->where(
                    $field,
                    $validated[$field]
                );
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Partial-match filters
    |--------------------------------------------------------------------------
    */

        $likeFilters = [
            'education',
            'occupation',
            'designation',
            'native_place',
            'any_disability',
        ];

        foreach ($likeFilters as $field) {

            if (
                array_key_exists($field, $validated)
                && $validated[$field] !== null
                && $validated[$field] !== ''
            ) {

                $query->where(
                    $field,
                    'LIKE',
                    '%' . $validated[$field] . '%'
                );
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Height
    |--------------------------------------------------------------------------
    |
    | Member height is stored as a string.
    |
    | Example:
    |
    | 5.5
    | 5ft 6in
    |
    | We will initially handle numeric height values.
    |
    */

        if (
            array_key_exists('height_from', $validated)
            || array_key_exists('height_to', $validated)
        ) {

            $heightFrom = $validated['height_from'] ?? 3;
            $heightTo   = $validated['height_to'] ?? 8;

            $query->whereRaw(
                "CAST(height AS DECIMAL(5,2)) BETWEEN ? AND ?",
                [
                    $heightFrom,
                    $heightTo,
                ]
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Select fields
    |--------------------------------------------------------------------------
    */

        $members = $query
            ->select([
                'id',
                'profile_id',
                'full_name',
                'birth_date_time',
                'gender',
                'height',

                'religion',
                'mother_tongue',
                'cast',
                'sub_cast',
                'gotra',
                'manglik',

                'marital_status',
                'no_of_child',

                'education',
                'employed_in',
                'occupation',
                'designation',
                'annual_income',

                'country_living_in',
                'state_living_in',
                'city_living_in',
                'native_place',

                'diet',
                'is_drinking',
                'is_smoking',
                'any_disability',

                'horoscope_needed',

                'photo',
                'profile_completed',
            ])
            ->orderByDesc('promoted')
            ->orderByDesc('id')
            ->paginate(20);

        /*
    |--------------------------------------------------------------------------
    | Format results
    |--------------------------------------------------------------------------
    */

        $members->getCollection()->transform(
            function ($member) {

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

                    'id' => $member->id,

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

                    'sub_cast' =>
                    $member->sub_cast,

                    'gotra' =>
                    $member->gotra,

                    'manglik' =>
                    $member->manglik,

                    'marital_status' =>
                    $member->marital_status,

                    'no_of_child' =>
                    $member->no_of_child,

                    'education' =>
                    $member->education,

                    'employed_in' =>
                    $member->employed_in,

                    'occupation' =>
                    $member->occupation,

                    'designation' =>
                    $member->designation,

                    'annual_income' =>
                    $member->annual_income,

                    'country_living_in' =>
                    $member->country_living_in,

                    'state_living_in' =>
                    $member->state_living_in,

                    'city_living_in' =>
                    $member->city_living_in,

                    'native_place' =>
                    $member->native_place,

                    'diet' =>
                    $member->diet,

                    'is_drinking' =>
                    $member->is_drinking,

                    'is_smoking' =>
                    $member->is_smoking,

                    'any_disability' =>
                    $member->any_disability,

                    'horoscope_needed' =>
                    $member->horoscope_needed,

                    'photo' =>
                    $member->photo,

                    'profile_completed' =>
                    (int) $member->profile_completed,
                ];
            }
        );

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'success' => true,

            'message' =>
            'Advanced search results fetched successfully.',

            'data' => [

                'members' =>
                $members->items(),

                'pagination' => [

                    'current_page' =>
                    $members->currentPage(),

                    'last_page' =>
                    $members->lastPage(),

                    'per_page' =>
                    $members->perPage(),

                    'total' =>
                    $members->total(),
                ],
            ],
        ]);
    }
}
