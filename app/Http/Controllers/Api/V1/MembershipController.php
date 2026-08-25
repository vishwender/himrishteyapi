<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\V1\ApplicationDatabaseService;
use Illuminate\Http\JsonResponse;

class MembershipController extends Controller
{
    public function __construct(
        private ApplicationDatabaseService $databaseService
    ) {}

    /**
     * Get all membership types.
     */
    public function index(): JsonResponse
    {
        $connection = $this->databaseService->connection();

        $memberships = $connection
            ->table('membership_type')
            ->select([
                'id',
                'plan_name',
                'plan_guide',
                'plan_description',
                'terms_and_conditions',
            ])
            ->orderBy('id')
            ->get()
            ->map(function ($membership) {

                return [
                    'id' =>
                    (int) $membership->id,

                    'name' =>
                    $membership->plan_name,

                    'plan_guide' =>
                    $membership->plan_guide,

                    'plan_description' =>
                    $membership->plan_description,

                    'terms_and_conditions' =>
                    $membership->terms_and_conditions,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Memberships fetched successfully.',
            'data' => $memberships,
        ]);
    }

    /**
     * Get plans belonging to a membership type.
     */
    public function plans(int $membershipTypeId): JsonResponse
    {
        $connection = $this->databaseService->connection();

        /*
        |--------------------------------------------------------------------------
        | Membership type
        |--------------------------------------------------------------------------
        */

        $membership = $connection
            ->table('membership_type')
            ->where('id', $membershipTypeId)
            ->first();

        if (!$membership) {

            return response()->json([
                'success' => false,
                'message' => 'Membership not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Plans
        |--------------------------------------------------------------------------
        */

        $plans = $connection
            ->table('membership_plans')
            ->where(
                'membership_type',
                $membershipTypeId
            )
            ->orderBy('duration_days')
            ->get([
                'id',
                'membership_type',
                'plan_name',
                'duration_days',
                'view_contact',
                'view_profile',
                'plan_cost',
                'discount_percentage',
                'final_cost',
            ])
            ->map(function ($plan) {

                return [
                    'id' =>
                    (int) $plan->id,

                    'plan_name' =>
                    $plan->plan_name,

                    'duration_days' =>
                    (int) $plan->duration_days,

                    'view_contact' =>
                    (int) $plan->view_contact,

                    'view_profile' =>
                    (int) $plan->view_profile,

                    'plan_cost' =>
                    (int) $plan->plan_cost,

                    'discount_percentage' =>
                    (int) $plan->discount_percentage,

                    'final_cost' =>
                    $plan->final_cost,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Membership plans fetched successfully.',
            'data' => [
                'membership' => [
                    'id' =>
                    (int) $membership->id,

                    'name' =>
                    $membership->plan_name,

                    'plan_guide' =>
                    $membership->plan_guide,

                    'plan_description' =>
                    $membership->plan_description,

                    'terms_and_conditions' =>
                    $membership->terms_and_conditions,
                ],

                'plans' =>
                $plans,
            ],
        ]);
    }
}
