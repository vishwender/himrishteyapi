<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SentInterest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SentInterestController extends Controller
{
    /**
     * Send interest to a profile.
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
        | Cannot send interest to own profile
        |--------------------------------------------------------------------------
        */

        if ((int) $member->id === $profileId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot send interest to your own profile.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check existing interest
        |--------------------------------------------------------------------------
        */

        $interest = SentInterest::query()
            ->where('member_id', $member->id)
            ->where('profile_id', $profileId)
            ->latest('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Existing interest
        |--------------------------------------------------------------------------
        */

        if ($interest) {
            return response()->json([
                'success' => true,
                'message' => 'Interest has already been sent to this profile.',
                'data' => [
                    'id' => $interest->id,
                    'profile_id' => $interest->profile_id,
                    'status' => $interest->status,
                    'sent' => true,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create interest
        |--------------------------------------------------------------------------
        */

        $interest = SentInterest::create([
            'member_id' => $member->id,
            'profile_id' => $profileId,
            'status' => '0',
            'created_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Interest sent successfully.',
            'data' => [
                'id' => $interest->id,
                'profile_id' => $interest->profile_id,
                'status' => $interest->status,
                'sent' => true,
                'created_at' => $interest->created_at
                    ? $interest->created_at->format('Y-m-d H:i:s')
                    : null,
            ],
        ], 201);
    }

    /**
     * Get interests sent by the logged-in member.
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

        $interests = SentInterest::query()
            ->where('member_id', $member->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Sent interests fetched successfully.',
            'data' => [
                'count' => $interests->count(),

                'interests' => $interests->map(function ($interest) {
                    return [
                        'id' => $interest->id,
                        'profile_id' => $interest->profile_id,
                        'status' => $interest->status,
                        'sent_at' => $interest->created_at
                            ? $interest->created_at->format('Y-m-d H:i:s')
                            : null,
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Check interest status for a profile.
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

        $interest = SentInterest::query()
            ->where('member_id', $member->id)
            ->where('profile_id', $profileId)
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'profile_id' => $profileId,
                'sent' => $interest !== null,
                'interest' => $interest ? [
                    'id' => $interest->id,
                    'status' => $interest->status,
                    'sent_at' => $interest->created_at
                        ? $interest->created_at->format('Y-m-d H:i:s')
                        : null,
                ] : null,
            ],
        ]);
    }

    /**
     * Get interests received by the logged-in member.
     */
    public function received(Request $request): JsonResponse
    {
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $interests = SentInterest::query()
            ->where('profile_id', $member->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Received interests fetched successfully.',
            'data' => [
                'count' => $interests->count(),

                'interests' => $interests->map(function ($interest) {
                    return [
                        'id' => $interest->id,

                        'member_id' => $interest->member_id,

                        'profile_id' => $interest->profile_id,

                        'status' => $interest->status,

                        'status_label' => match ((string) $interest->status) {
                            '0' => 'Pending',
                            '1' => 'Accepted',
                            '2' => 'Rejected',
                            default => 'Unknown',
                        },

                        'received_at' => $interest->created_at
                            ? $interest->created_at->format('Y-m-d H:i:s')
                            : null,
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Accept a received interest.
     */
    public function accept(
        Request $request,
        int $id
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
    | Find interest
    |--------------------------------------------------------------------------
    |
    | The logged-in member MUST be the recipient.
    |
    */

        $interest = SentInterest::query()
            ->where('id', $id)
            ->where('profile_id', $member->id)
            ->first();

        if (!$interest) {
            return response()->json([
                'success' => false,
                'message' => 'Interest not found.',
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Already accepted
    |--------------------------------------------------------------------------
    */

        if ((string) $interest->status === '1') {
            return response()->json([
                'success' => true,
                'message' => 'Interest has already been accepted.',
                'data' => [
                    'id' => $interest->id,
                    'member_id' => $interest->member_id,
                    'profile_id' => $interest->profile_id,
                    'status' => '1',
                    'status_label' => 'Accepted',
                ],
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Cannot accept rejected interest
    |--------------------------------------------------------------------------
    */

        if ((string) $interest->status === '2') {
            return response()->json([
                'success' => false,
                'message' => 'A rejected interest cannot be accepted.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Accept
    |--------------------------------------------------------------------------
    */

        $interest->status = '1';
        $interest->save();

        return response()->json([
            'success' => true,
            'message' => 'Interest accepted successfully.',
            'data' => [
                'id' => $interest->id,
                'member_id' => $interest->member_id,
                'profile_id' => $interest->profile_id,
                'status' => '1',
                'status_label' => 'Accepted',
            ],
        ]);
    }

    /**
     * Reject a received interest.
     */
    public function reject(
        Request $request,
        int $id
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
    | Find interest
    |--------------------------------------------------------------------------
    */

        $interest = SentInterest::query()
            ->where('id', $id)
            ->where('profile_id', $member->id)
            ->first();

        if (!$interest) {
            return response()->json([
                'success' => false,
                'message' => 'Interest not found.',
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Already rejected
    |--------------------------------------------------------------------------
    */

        if ((string) $interest->status === '2') {
            return response()->json([
                'success' => true,
                'message' => 'Interest has already been rejected.',
                'data' => [
                    'id' => $interest->id,
                    'member_id' => $interest->member_id,
                    'profile_id' => $interest->profile_id,
                    'status' => '2',
                    'status_label' => 'Rejected',
                ],
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Cannot reject accepted interest
    |--------------------------------------------------------------------------
    */

        if ((string) $interest->status === '1') {
            return response()->json([
                'success' => false,
                'message' => 'An accepted interest cannot be rejected.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Reject
    |--------------------------------------------------------------------------
    */

        $interest->status = '2';
        $interest->save();

        return response()->json([
            'success' => true,
            'message' => 'Interest rejected successfully.',
            'data' => [
                'id' => $interest->id,
                'member_id' => $interest->member_id,
                'profile_id' => $interest->profile_id,
                'status' => '2',
                'status_label' => 'Rejected',
            ],
        ]);
    }

    /**
     * Cancel a sent interest.
     */
    public function cancel(
        Request $request,
        $id
    ): JsonResponse {

        $id = (int) $id;

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
    | Find interest
    |--------------------------------------------------------------------------
    |
    | The logged-in member MUST be the sender.
    |
    */

        $interest = SentInterest::query()
            ->where('id', $id)
            ->where('member_id', $member->id)
            ->first();

        if (!$interest) {
            return response()->json([
                'success' => false,
                'message' => 'Interest not found.',
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Only pending interests can be cancelled
    |--------------------------------------------------------------------------
    */

        if ((string) $interest->status === '1') {
            return response()->json([
                'success' => false,
                'message' => 'An accepted interest cannot be cancelled.',
            ], 422);
        }

        if ((string) $interest->status === '2') {
            return response()->json([
                'success' => false,
                'message' => 'A rejected interest cannot be cancelled.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Cancel pending interest
    |--------------------------------------------------------------------------
    |
    | The legacy table has no cancelled status.
    | Therefore the pending record is removed.
    |
    */

        $profileId = $interest->profile_id;

        $interest->delete();

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'success' => true,
            'message' => 'Interest cancelled successfully.',
            'data' => [
                'profile_id' => $profileId,
                'cancelled' => true,
            ],
        ]);
    }
}
