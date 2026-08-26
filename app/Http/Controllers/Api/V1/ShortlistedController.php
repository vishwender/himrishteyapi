<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShortListed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortlistedController extends Controller
{
    /**
     * Get logged-in member's shortlisted profiles.
     */
    public function index(Request $request): JsonResponse
    {
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $shortlisted = ShortListed::query()
            ->where('member_id', $member->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Shortlisted profiles fetched successfully.',
            'data' => [
                'count' => $shortlisted->count(),

                'profiles' => $shortlisted->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'profile_id' => $item->profile_id,
                        'created_at' => $item->created_at
                            ? $item->created_at->format('Y-m-d H:i:s')
                            : null,
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Shortlist a profile.
     */
    public function store(
        Request $request,
        int $profileId
    ): JsonResponse {

        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Cannot shortlist own profile
        |--------------------------------------------------------------------------
        */

        if ((int) $member->id === $profileId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot shortlist your own profile.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check existing shortlist
        |--------------------------------------------------------------------------
        */

        $existing = ShortListed::query()
            ->where('member_id', $member->id)
            ->where('profile_id', $profileId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Profile is already shortlisted.',
                'data' => [
                    'id' => $existing->id,
                    'profile_id' => $existing->profile_id,
                    'shortlisted' => true,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create shortlist
        |--------------------------------------------------------------------------
        */

        $shortlisted = ShortListed::create([
            'member_id' => $member->id,
            'profile_id' => $profileId,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile shortlisted successfully.',
            'data' => [
                'id' => $shortlisted->id,
                'profile_id' => $shortlisted->profile_id,
                'shortlisted' => true,
                'created_at' => $shortlisted->created_at
                    ? $shortlisted->created_at->format('Y-m-d H:i:s')
                    : null,
            ],
        ], 201);
    }

    /**
     * Remove profile from shortlist.
     */
    public function destroy(
        Request $request,
        int $profileId
    ): JsonResponse {

        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $shortlisted = ShortListed::query()
            ->where('member_id', $member->id)
            ->where('profile_id', $profileId)
            ->first();

        if (!$shortlisted) {
            return response()->json([
                'success' => true,
                'message' => 'Profile is not shortlisted.',
                'data' => [
                    'profile_id' => $profileId,
                    'shortlisted' => false,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Remove shortlist
        |--------------------------------------------------------------------------
        */

        $shortlisted->delete();

        return response()->json([
            'success' => true,
            'message' => 'Profile removed from shortlist successfully.',
            'data' => [
                'profile_id' => $profileId,
                'shortlisted' => false,
            ],
        ]);
    }

    /**
     * Check whether a profile is shortlisted.
     */
    public function show(
        Request $request,
        int $profileId
    ): JsonResponse {

        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $shortlisted = ShortListed::query()
            ->where('member_id', $member->id)
            ->where('profile_id', $profileId)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'profile_id' => $profileId,
                'shortlisted' => $shortlisted,
            ],
        ]);
    }
}