<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberWallet;
use App\Models\ProfileViewed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProfileContactController extends Controller
{
    /**
     * Unlock a member's contact details using the current profile-range rate.
     */
    public function unlock(Request $request, int $profileId): JsonResponse
    {
        /** @var Member|null $member */
        $member = $request->user();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ((int) $member->id === $profileId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot unlock your own contact details.',
            ], 422);
        }

        $profile = Member::query()
            ->whereKey($profileId)
            ->whereRaw("LOWER(TRIM(active)) = 'yes'")
            ->where(function ($query) {
                $query->whereNull('profile_hide')
                    ->orWhere('profile_hide', '')
                    ->orWhereRaw("LOWER(TRIM(profile_hide)) = 'no'");
            })
            ->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found.',
            ], 404);
        }

        try {
            $result = DB::connection('application')->transaction(
                function () use ($member, $profile) {
                    /*
                     * The member row always exists, even when no wallet does. Locking
                     * it serializes simultaneous unlock requests for this member.
                     */
                    Member::query()
                        ->whereKey($member->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $wallet = MemberWallet::query()
                        ->where('member_id', (string) $member->id)
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();

                    $currentBalance = $wallet?->balance_value ?? 0.0;

                    $existingUnlock = ProfileViewed::query()
                        ->where('member_id', $member->id)
                        ->where('viewed_profile_id', $profile->id)
                        ->first();

                    if ($existingUnlock) {
                        return [
                            'already_unlocked' => true,
                            'coins_deducted' => 0.0,
                            'wallet_balance' => $currentBalance,
                            'profile_view_number' => null,
                        ];
                    }

                    $unlockedCount = ProfileViewed::query()
                        ->where('member_id', $member->id)
                        ->distinct()
                        ->count('viewed_profile_id');

                    $profileViewNumber = $unlockedCount + 1;

                    $priceRange = DB::connection('application')
                        ->table('profile_ranges')
                        ->whereRaw(
                            '? BETWEEN CAST(range_from AS UNSIGNED) AND CAST(range_to AS UNSIGNED)',
                            [$profileViewNumber]
                        )
                        ->orderByRaw('CAST(range_from AS UNSIGNED)')
                        ->first();

                    if (! $priceRange || ! is_numeric($priceRange->rate)) {
                        throw new RuntimeException(
                            "No profile-view price is configured for view {$profileViewNumber}."
                        );
                    }

                    $price = (float) $priceRange->rate;

                    if ($price < 0) {
                        throw new RuntimeException('The configured profile-view price is invalid.');
                    }

                    if ($currentBalance < $price) {
                        return [
                            'insufficient_balance' => true,
                            'required_coins' => $price,
                            'wallet_balance' => $currentBalance,
                            'profile_view_number' => $profileViewNumber,
                        ];
                    }

                    $newBalance = $currentBalance - $price;

                    MemberWallet::query()->create([
                        'member_id' => (string) $member->id,
                        'wallet_balance' => (string) $newBalance,
                        'amount_deducted' => (string) $price,
                        'amount_added' => '0',
                        'created_at' => now(),
                        'update_at' => now(),
                        'added_by' => 'Profile contact unlock',
                    ]);

                    ProfileViewed::query()->create([
                        'member_id' => $member->id,
                        'viewed_profile_id' => $profile->id,
                        'created_at' => now(),
                    ]);

                    return [
                        'already_unlocked' => false,
                        'coins_deducted' => $price,
                        'wallet_balance' => $newBalance,
                        'profile_view_number' => $profileViewNumber,
                        'price_range' => [
                            'from' => (int) $priceRange->range_from,
                            'to' => (int) $priceRange->range_to,
                        ],
                    ];
                }
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to unlock contact details.',
            ], 500);
        }

        if ($result['insufficient_balance'] ?? false) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance.',
                'data' => [
                    'required_coins' => $result['required_coins'],
                    'wallet_balance' => $result['wallet_balance'],
                    'profile_view_number' => $result['profile_view_number'],
                ],
            ], 402);
        }

        return response()->json([
            'success' => true,
            'message' => ($result['already_unlocked'] ?? false)
                ? 'Contact details were already unlocked.'
                : 'Contact details unlocked successfully.',
            'data' => [
                'profile' => [
                    'id' => $profile->id,
                    'profile_id' => $profile->profile_id,
                    'full_name' => $profile->full_name,
                ],
                'contact_details' => [
                    'email' => $profile->email,
                    'mobile_number' => $profile->mobile_number,
                    'alternate_number' => $profile->alternate_number,
                    'whatsapp_number' => $profile->whatsapp_number,
                ],
                'already_unlocked' => $result['already_unlocked'],
                'coins_deducted' => $result['coins_deducted'],
                'wallet_balance' => $result['wallet_balance'],
                'profile_view_number' => $result['profile_view_number'],
                'price_range' => $result['price_range'] ?? null,
            ],
        ]);
    }
}
