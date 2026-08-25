<?php

namespace App\Services;

use Razorpay\Api\Api;

class RazorpayService
{
    protected Api $api;

    public function __construct()
    {
        $this->api = new Api(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        );
    }

    /**
     * Create Razorpay order.
     */
    public function createOrder(
        float $amount,
        string $receipt
    ) {
        return $this->api->order->create([

            'receipt' => $receipt,

            'amount' => (int) round($amount * 100),

            'currency' => 'INR',

        ]);
    }

    /**
     * Verify Razorpay payment signature.
     */
    public function verifyPaymentSignature(
        string $orderId,
        string $paymentId,
        string $signature
    ): bool {

        try {

            $this->api->utility->verifyPaymentSignature([

                'razorpay_order_id' =>
                $orderId,

                'razorpay_payment_id' =>
                $paymentId,

                'razorpay_signature' =>
                $signature,

            ]);

            return true;
        } catch (\Throwable $e) {

            return false;
        }
    }

    /**
     * Fetch payment from Razorpay.
     */
    public function fetchPayment(string $paymentId)
    {
        return $this->api
            ->payment
            ->fetch($paymentId);
    }
}
