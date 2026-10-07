<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\StripeService;
use App\Services\PayPalService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $stripeService;
    protected $paypalService;

    public function __construct(StripeService $stripeService, PayPalService $paypalService)
    {
        $this->stripeService = $stripeService;
        $this->paypalService = $paypalService;
    }

    /**
     * Create Stripe payment intent
     */
    public function createStripeIntent(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,_id',
        ]);

        $order = Order::findOrFail($request->order_id);

        // Check if user owns the order
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $result = $this->stripeService->createPaymentIntent(
            $order->total,
            'usd',
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]
        );

        if ($result['success']) {
            $order->payment_transaction_id = $result['payment_intent_id'];
            $order->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'client_secret' => $result['client_secret'],
                    'payment_intent_id' => $result['payment_intent_id'],
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Payment failed',
        ], 400);
    }

    /**
     * Confirm Stripe payment
     */
    public function confirmStripePayment(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        $result = $this->stripeService->confirmPayment($request->payment_intent_id);

        if ($result['success'] && $result['status'] === 'succeeded') {
            $order = Order::where('payment_transaction_id', $request->payment_intent_id)->first();

            if ($order) {
                $order->payment_status = 'paid';
                $order->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'data' => $order,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment confirmation failed',
        ], 400);
    }

    /**
     * Create PayPal order
     */
    public function createPayPalOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,_id',
        ]);

        $order = Order::findOrFail($request->order_id);

        // Check if user owns the order
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $result = $this->paypalService->createOrder(
            $order->total,
            'USD',
            [
                'description' => "Order #{$order->order_number}",
                'return_url' => config('app.frontend_url') . '/payment/success',
                'cancel_url' => config('app.frontend_url') . '/payment/cancel',
            ]
        );

        if ($result['success']) {
            $order->payment_transaction_id = $result['order_id'];
            $order->save();

            return response()->json([
                'success' => true,
                'data' => $result['data'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Payment failed',
        ], 400);
    }

    /**
     * Capture PayPal order
     */
    public function capturePayPalOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
        ]);

        $result = $this->paypalService->captureOrder($request->order_id);

        if ($result['success']) {
            $order = Order::where('payment_transaction_id', $request->order_id)->first();

            if ($order) {
                $order->payment_status = 'paid';
                $order->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment captured successfully',
                'data' => $order,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment capture failed',
        ], 400);
    }

    /**
     * Stripe webhook handler
     */
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        $result = $this->stripeService->handleWebhook($payload, $signature);

        if ($result['success']) {
            $event = $result['event'];

            // Handle different event types
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event->data->object;
                    $order = Order::where('payment_transaction_id', $paymentIntent->id)->first();
                    
                    if ($order) {
                        $order->payment_status = 'paid';
                        $order->save();
                    }
                    break;

                case 'payment_intent.payment_failed':
                    $paymentIntent = $event->data->object;
                    $order = Order::where('payment_transaction_id', $paymentIntent->id)->first();
                    
                    if ($order) {
                        $order->payment_status = 'failed';
                        $order->save();
                    }
                    break;
            }

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 400);
    }

    /**
     * PayPal webhook handler
     */
    public function paypalWebhook(Request $request)
    {
        // Implement PayPal webhook verification and handling
        $event = $request->all();

        // Handle different event types
        if (isset($event['event_type'])) {
            switch ($event['event_type']) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    // Handle successful payment
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                    // Handle failed payment
                    break;
            }
        }

        return response()->json(['success' => true]);
    }
}
