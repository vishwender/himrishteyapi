<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeleteProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeleteProfileRequestController extends Controller
{
    /**
     * Create profile deletion request.
     */
    public function store(Request $request): JsonResponse
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
        | Validate request
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Check existing pending request
        |--------------------------------------------------------------------------
        */

        $existingRequest = DeleteProfileRequest::query()
            ->where('user_id', $member->id)
            ->where('status', 0)
            ->latest('id')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending profile deletion request.',
                'data' => [
                    'id' => $existingRequest->id,
                    'status' => $existingRequest->status,
                    'reason' => $existingRequest->reason,
                    'date' => $existingRequest->date,
                ],
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | Create request
        |--------------------------------------------------------------------------
        */

        $deleteRequest = DeleteProfileRequest::create([
            'user_id' => $member->id,

            'reason' => $validated['reason'] ?? null,

            'request_by' => $member->id,

            'date' => now()->format('Y-m-d H:i:s'),

            'status' => 0,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Profile deletion request submitted successfully.',
            'data' => [
                'id' => $deleteRequest->id,
                'user_id' => $deleteRequest->user_id,
                'reason' => $deleteRequest->reason,
                'request_by' => $deleteRequest->request_by,
                'date' => $deleteRequest->date,
                'status' => $deleteRequest->status,
            ],
        ], 201);
    }

    /**
     * Get logged-in member's latest profile deletion request.
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
        | Get requests
        |--------------------------------------------------------------------------
        */

        $requests = DeleteProfileRequest::query()
            ->where('user_id', $member->id)
            ->orderByDesc('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Profile deletion requests fetched successfully.',
            'data' => [
                'requests' => $requests->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'reason' => $item->reason,
                        'request_by' => $item->request_by,
                        'date' => $item->date,
                        'status' => (int) $item->status,
                    ];
                })->values(),
            ],
        ]);
    }
}
