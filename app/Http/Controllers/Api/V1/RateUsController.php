<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RateUsController extends Controller
{
    /**
     * Submit a rating / feedback.
     */
    public function store(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Logged-in member
        |--------------------------------------------------------------------------
        */

        /** @var Member $member */
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'rating' => [
                'required',
                'integer',
                'min:1',
                'max:5',
            ],

            'description' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Save rating
        |--------------------------------------------------------------------------
        |
        | The current application database connection is already
        | established by ResolveApplication middleware.
        |
        */

        $ratingId = DB::connection('application')
            ->table('user_rating')
            ->insertGetId([
                'name' => $member->full_name ?? '',
                'email' => $member->email ?? '',
                'profile_id' => $member->profile_id ?? '',
                'rating' => (string) $validated['rating'],
                'description' => $validated['description'],
                'submitted_on' => now()->format('Y-m-d H:i:s'),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your rating and feedback.',
            'data' => [
                'id' => $ratingId,
                'profile_id' => $member->profile_id,
                'rating' => $validated['rating'],
                'description' => $validated['description'],
                'submitted_on' => now()->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }
}
