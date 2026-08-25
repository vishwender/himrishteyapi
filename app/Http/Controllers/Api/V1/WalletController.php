<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MemberWallet;
use App\Models\MemberWalletPayment;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Razorpay\Api\Api;

class WalletController extends Controller
{
    protected RazorpayService $razorpay;

    public function __construct(RazorpayService $razorpay)
    {
        $this->razorpay = $razorpay;
    }

    /**
     * Get logged-in member wallet.
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

        /*
        |--------------------------------------------------------------------------
        | Get latest wallet record
        |--------------------------------------------------------------------------
        */

        $wallet = MemberWallet::query()
            ->where('member_id', (string) $member->id)
            ->orderByDesc('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | No wallet record
        |--------------------------------------------------------------------------
        */

        if (!$wallet) {
            return response()->json([
                'success' => true,
                'message' => 'Wallet fetched successfully.',
                'data' => [
                    'member_id' => $member->id,
                    'profile_id' => $member->profile_id,
                    'balance' => 0,
                    'currency' => 'INR',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Wallet fetched successfully.',
            'data' => [
                'member_id' => $member->id,
                'profile_id' => $member->profile_id,
                'balance' => $wallet->balance_value,
                'currency' => 'INR',
            ],
        ]);
    }

    /**
     * Get logged-in member wallet transactions.
     */
    public function transactions(Request $request): JsonResponse
    {
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Get wallet records
        |--------------------------------------------------------------------------
        */

        $walletRecords = MemberWallet::query()
            ->where('member_id', (string) $member->id)
            ->orderByDesc('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Current balance
        |--------------------------------------------------------------------------
        */

        $latestWallet = $walletRecords->first();

        $currentBalance = $latestWallet
            ? $latestWallet->balance_value
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Build transactions
        |--------------------------------------------------------------------------
        */

        $transactions = [];

        foreach ($walletRecords as $wallet) {

            $added = $wallet->added_amount;
            $deducted = $wallet->deducted_amount;

            /*
            |--------------------------------------------------------------------------
            | Credit
            |--------------------------------------------------------------------------
            */

            if ($added > 0) {

                $transactions[] = [
                    'id' => $wallet->id,
                    'type' => 'credit',
                    'amount' => $added,
                    'balance' => $wallet->balance_value,
                    'added_by' => $wallet->added_by,
                    'date' => $this->formatWalletDate(
                        $wallet->created_at
                    ),
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Debit
            |--------------------------------------------------------------------------
            */

            if ($deducted > 0) {

                $transactions[] = [
                    'id' => $wallet->id,
                    'type' => 'debit',
                    'amount' => $deducted,
                    'balance' => $wallet->balance_value,
                    'added_by' => $wallet->added_by,
                    'date' => $this->formatWalletDate(
                        $wallet->created_at
                    ),
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Wallet transactions fetched successfully.',

            'data' => [
                'member_id' => $member->id,
                'profile_id' => $member->profile_id,

                'current_balance' => $currentBalance,

                'currency' => 'INR',

                'transactions' => $transactions,
            ],
        ]);
    }

    /**
     * Create Razorpay order for adding money.
     */
    public function addMoney(Request $request): JsonResponse
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
        | Validate amount
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:100000',
            ],
        ]);

        $amount = (float) $validated['amount'];

        /*
        |--------------------------------------------------------------------------
        | Generate receipt
        |--------------------------------------------------------------------------
        */

        $receipt = 'wallet_' .
            $member->id . '_' .
            Str::random(12);

        /*
        |--------------------------------------------------------------------------
        | Create Razorpay order
        |--------------------------------------------------------------------------
        */

        try {

            $order = $this->razorpay->createOrder(
                $amount,
                $receipt
            );
        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create payment order.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'message' =>
            'Payment order created successfully.',

            'data' => [

                'order_id' =>
                $order['id'],

                'amount' =>
                $amount,

                'amount_paise' =>
                (int) round($amount * 100),

                'currency' =>
                'INR',

                'razorpay_key' =>
                config('services.razorpay.key_id'),

                'member' => [
                    'id' =>
                    $member->id,

                    'profile_id' =>
                    $member->profile_id,

                    'name' =>
                    $member->full_name,

                    'email' =>
                    $member->email,

                    'mobile_number' =>
                    $member->mobile_number,
                ],
            ],
        ]);
    }

    /**
     * Verify Razorpay wallet payment and credit wallet.
     */
    public function verifyAddMoney(Request $request): JsonResponse
    {
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'razorpay_order_id' => [
                'required',
                'string',
            ],

            'razorpay_payment_id' => [
                'required',
                'string',
            ],

            'razorpay_signature' => [
                'required',
                'string',
            ],
        ]);

        /*
    |--------------------------------------------------------------------------
    | Razorpay API
    |--------------------------------------------------------------------------
    */

        try {

            $api = new Api(
                config('services.razorpay.key_id'),
                config('services.razorpay.key_secret')
            );

            /*
        |--------------------------------------------------------------------------
        | Verify Razorpay signature
        |--------------------------------------------------------------------------
        */

            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' =>
                $validated['razorpay_order_id'],

                'razorpay_payment_id' =>
                $validated['razorpay_payment_id'],

                'razorpay_signature' =>
                $validated['razorpay_signature'],
            ]);

            /*
        |--------------------------------------------------------------------------
        | Fetch payment
        |--------------------------------------------------------------------------
        */

            $payment = $api->payment->fetch(
                $validated['razorpay_payment_id']
            );
        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed.',
                'error' => $e->getMessage(),
            ], 400);
        }

        /*
    |--------------------------------------------------------------------------
    | Verify payment belongs to this wallet order
    |--------------------------------------------------------------------------
    */

        if (
            isset($payment['order_id']) &&
            $payment['order_id'] !==
            $validated['razorpay_order_id']
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Payment does not belong to this order.',
            ], 400);
        }

        /*
    |--------------------------------------------------------------------------
    | Payment must be captured
    |--------------------------------------------------------------------------
    */

        if (
            !isset($payment['status']) ||
            $payment['status'] !== 'captured'
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Payment has not been captured.',
                'payment_status' =>
                $payment['status'] ?? null,
            ], 400);
        }

        /*
    |--------------------------------------------------------------------------
    | Amount actually paid
    |--------------------------------------------------------------------------
    */

        $paidAmount =
            ((int) $payment['amount']) / 100;

        if ($paidAmount <= 0) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid payment amount.',
            ], 400);
        }

        /*
    |--------------------------------------------------------------------------
    | Update wallet
    |--------------------------------------------------------------------------
    */

        try {

            $result = DB::connection('application')
                ->transaction(function () use (
                    $member,
                    $validated,
                    $paidAmount
                ) {

                    /*
                |--------------------------------------------------------------------------
                | Prevent duplicate payment
                |--------------------------------------------------------------------------
                */

                    $existingPayment =
                        MemberWalletPayment::query()
                        ->where(
                            'payment_id',
                            $validated['razorpay_payment_id']
                        )
                        ->first();

                    if ($existingPayment) {

                        $wallet =
                            MemberWallet::query()
                            ->where(
                                'member_id',
                                (string) $member->id
                            )
                            ->orderByDesc('id')
                            ->first();

                        return [
                            'already_processed' => true,

                            'balance' =>
                            $wallet
                                ? $wallet->balance_value
                                : 0,

                            'payment_id' =>
                            $existingPayment->payment_id,
                        ];
                    }

                    /*
                |--------------------------------------------------------------------------
                | Get latest wallet
                |--------------------------------------------------------------------------
                */

                    $wallet =
                        MemberWallet::query()
                        ->where(
                            'member_id',
                            (string) $member->id
                        )
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();

                    /*
                |--------------------------------------------------------------------------
                | Current balance
                |--------------------------------------------------------------------------
                */

                    $currentBalance =
                        $wallet
                        ? (float) $wallet->balance_value
                        : 0;

                    /*
                |--------------------------------------------------------------------------
                | New balance
                |--------------------------------------------------------------------------
                */

                    $newBalance =
                        $currentBalance + $paidAmount;

                    /*
                |--------------------------------------------------------------------------
                | Update existing wallet
                |--------------------------------------------------------------------------
                */

                    if ($wallet) {

                        $wallet->wallet_balance =
                            (string) $newBalance;

                        $wallet->amount_added =
                            (string) $paidAmount;

                        $wallet->amount_deducted =
                            '0';

                        $wallet->added_by =
                            'Razorpay';

                        $wallet->save();
                    } else {

                        /*
                    |--------------------------------------------------------------------------
                    | Create wallet
                    |--------------------------------------------------------------------------
                    */

                        $wallet = new MemberWallet();

                        $wallet->member_id =
                            (string) $member->id;

                        $wallet->wallet_balance =
                            (string) $newBalance;

                        $wallet->amount_added =
                            (string) $paidAmount;

                        $wallet->amount_deducted =
                            '0';

                        $wallet->added_by =
                            'Razorpay';

                        $wallet->save();
                    }

                    /*
                |--------------------------------------------------------------------------
                | Store payment
                |--------------------------------------------------------------------------
                */

                    $walletPayment =
                        new MemberWalletPayment();

                    $walletPayment->payment_date =
                        now();

                    $walletPayment->member_id =
                        (string) $member->id;

                    $walletPayment->amount =
                        (string) $paidAmount;

                    $walletPayment->payment_id =
                        $validated['razorpay_payment_id'];

                    $walletPayment->remarks =
                        'Wallet recharge via Razorpay';

                    $walletPayment->save();

                    return [
                        'already_processed' => false,

                        'balance' =>
                        $newBalance,

                        'payment_id' =>
                        $walletPayment->payment_id,

                        'amount_added' =>
                        $paidAmount,
                    ];
                });
        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update wallet.',
            ], 500);
        }

        /*
    |--------------------------------------------------------------------------
    | Already processed
    |--------------------------------------------------------------------------
    */

        if ($result['already_processed']) {

            return response()->json([
                'success' => true,

                'message' =>
                'Payment has already been processed.',

                'data' => [
                    'payment_id' =>
                    $result['payment_id'],

                    'wallet_balance' =>
                    $result['balance'],

                    'currency' => 'INR',
                ],
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'success' => true,

            'message' =>
            'Money added to wallet successfully.',

            'data' => [
                'payment_id' =>
                $result['payment_id'],

                'amount_added' =>
                $result['amount_added'],

                'wallet_balance' =>
                $result['balance'],

                'currency' => 'INR',
            ],
        ]);
    }

    /**
     * Format legacy wallet date.
     */
    private function formatWalletDate($date): ?string
    {
        if (
            empty($date) ||
            $date === '0000-00-00 00:00:00'
        ) {
            return null;
        }

        try {
            return date(
                'Y-m-d H:i:s',
                strtotime($date)
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create Razorpay order for adding money to wallet.
     */
    public function createAddMoneyOrder(Request $request): JsonResponse
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
    | Validate amount
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:100000',
            ],
        ]);

        $amount = (float) $validated['amount'];

        /*
    |--------------------------------------------------------------------------
    | Razorpay amount is in paise
    |--------------------------------------------------------------------------
    */

        $amountInPaise = (int) round($amount * 100);

        /*
    |--------------------------------------------------------------------------
    | Create Razorpay order
    |--------------------------------------------------------------------------
    */

        try {

            $api = new Api(
                config('services.razorpay.key_id'),
                config('services.razorpay.key_secret')
            );

            $order = $api->order->create([
                'receipt' => 'wallet_' . $member->id . '_' . time(),

                'amount' => $amountInPaise,

                'currency' => 'INR',

                'notes' => [
                    'member_id' => (string) $member->id,
                    'profile_id' => (string) $member->profile_id,
                    'purpose' => 'wallet_add_money',
                ],
            ]);

            /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

            return response()->json([

                'success' => true,

                'message' =>
                'Wallet payment order created successfully.',

                'data' => [

                    'order_id' =>
                    $order['id'],

                    'amount' =>
                    $amount,

                    'amount_in_paise' =>
                    $amountInPaise,

                    'currency' =>
                    'INR',

                    'member_id' =>
                    $member->id,

                    'profile_id' =>
                    $member->profile_id,

                    'razorpay_key' =>
                    config('services.razorpay.key_id'),
                ],

            ], 201);
        } catch (\Throwable $e) {

            return response()->json([

                'success' => false,

                'message' =>
                'Unable to create payment order.',

                'error' =>
                $e->getMessage(),

            ], 500);
        }
    }
}
