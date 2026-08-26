<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProfileLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileLikeController extends Controller
{
    /**
     * Like a member profile.
     */
    public function store(
        Request $request,
        int $memberId
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | Get authenticated member
        |--------------------------------------------------------------------------
        */

        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Cannot like own profile
        |--------------------------------------------------------------------------
        */

        if ((int) $member->id === $memberId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot like your own profile.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check existing like
        |--------------------------------------------------------------------------
        */

        $like = ProfileLike::query()
            ->where('user_id', $member->id)
            ->where('like_profile_id', $memberId)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Already liked
        |--------------------------------------------------------------------------
        */

        if ($like && (int) $like->status === 1) {
            return response()->json([
                'success' => true,
                'message' => 'Profile is already liked.',
                'data' => [
                    'profile_id' => $memberId,
                    'liked' => true,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Reactivate existing like
        |--------------------------------------------------------------------------
        */

        if ($like) {

            $like->status = 1;
            $like->save();
        } else {

            /*
            |--------------------------------------------------------------------------
            | Create new like
            |--------------------------------------------------------------------------
            */

            $like = ProfileLike::create([
                'user_id' => $member->id,
                'like_profile_id' => $memberId,
                'status' => 1,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Profile liked successfully.',
            'data' => [
                'id' => $like->id,
                'profile_id' => $memberId,
                'liked' => true,
            ],
        ], 201);
    }

    /**
     * Unlike a member profile.
     */
    public function destroy(
        Request $request,
        int $memberId
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | Get authenticated member
        |--------------------------------------------------------------------------
        */

        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Find like
        |--------------------------------------------------------------------------
        */

        $like = ProfileLike::query()
            ->where('user_id', $member->id)
            ->where('like_profile_id', $memberId)
            ->where('status', 1)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Already not liked
        |--------------------------------------------------------------------------
        */

        if (!$like) {
            return response()->json([
                'success' => true,
                'message' => 'Profile is not liked.',
                'data' => [
                    'profile_id' => $memberId,
                    'liked' => false,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Disable like
        |--------------------------------------------------------------------------
        |
        | We keep the legacy record and simply change status to 0.
        |
        */

        $like->status = 0;
        $like->save();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Profile unliked successfully.',
            'data' => [
                'profile_id' => $memberId,
                'liked' => false,
            ],
        ]);
    }

    /**
     * Get profiles liked by the logged-in member.
     */
    public function index(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Get authenticated member
        |--------------------------------------------------------------------------
        */

        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Get active likes
        |--------------------------------------------------------------------------
        */

        $likes = ProfileLike::query()
            ->where('user_id', $member->id)
            ->where('status', 1)
            ->orderByDesc('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Liked profiles fetched successfully.',
            'data' => [
                'count' => $likes->count(),

                'profiles' => $likes->map(function ($like) {
                    return [
                        'like_id' => $like->id,
                        'profile_id' => $like->like_profile_id,
                        'liked' => true,
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Check whether the logged-in member likes a profile.
     */
    public function show(
        Request $request,
        int $memberId
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | Get authenticated member
        |--------------------------------------------------------------------------
        */

        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Check like
        |--------------------------------------------------------------------------
        */

        $liked = ProfileLike::query()
            ->where('user_id', $member->id)
            ->where('like_profile_id', $memberId)
            ->where('status', 1)
            ->exists();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'data' => [
                'profile_id' => $memberId,
                'liked' => $liked,
            ],
        ]);
    }
}
