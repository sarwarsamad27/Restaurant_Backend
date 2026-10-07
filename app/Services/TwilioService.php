<?php

namespace App\Services;

use Twilio\Rest\Client;
use Exception;

class TwilioService
{
    protected $client;
    protected $from;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->from = config('services.twilio.phone');

        if ($sid && $token) {
            $this->client = new Client($sid, $token);
        }
    }

    /**
     * Send SMS notification
     */
    public function sendSMS($to, $message)
    {
        try {
            if (!$this->client) {
                \Log::warning('Twilio not configured. SMS not sent.');
                return false;
            }

            $this->client->messages->create($to, [
                'from' => $this->from,
                'body' => $message
            ]);

            return true;
        } catch (Exception $e) {
            \Log::error('Twilio SMS Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send order confirmation SMS
     */
    public function sendOrderConfirmation($phone, $orderNumber, $restaurantName)
    {
        $message = "Your order #{$orderNumber} from {$restaurantName} has been confirmed! We'll notify you when it's ready.";
        return $this->sendSMS($phone, $message);
    }

    /**
     * Send order status update SMS
     */
    public function sendOrderStatusUpdate($phone, $orderNumber, $status)
    {
        $statusMessages = [
            'preparing' => "Your order #{$orderNumber} is being prepared.",
            'ready' => "Your order #{$orderNumber} is ready for pickup!",
            'out_for_delivery' => "Your order #{$orderNumber} is out for delivery!",
            'delivered' => "Your order #{$orderNumber} has been delivered. Enjoy your meal!",
            'cancelled' => "Your order #{$orderNumber} has been cancelled.",
        ];

        $message = $statusMessages[$status] ?? "Order #{$orderNumber} status updated to: {$status}";
        return $this->sendSMS($phone, $message);
    }

    /**
     * Send driver assignment notification
     */
    public function sendDriverAssignment($phone, $orderNumber, $driverName)
    {
        $message = "Driver {$driverName} has been assigned to your order #{$orderNumber}.";
        return $this->sendSMS($phone, $message);
    }

    /**
     * Send promotional SMS
     */
    public function sendPromotion($phone, $promoMessage)
    {
        return $this->sendSMS($phone, $promoMessage);
    }
}
