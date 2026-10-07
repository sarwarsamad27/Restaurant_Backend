<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    /**
     * Create notification for user
     */
    public function createNotification($userId, $type, $title, $message, $data = [])
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Send order notification
     */
    public function sendOrderNotification($order, $status)
    {
        $user = $order->user;
        $restaurant = $order->restaurant;

        $titles = [
            'confirmed' => 'Order Confirmed',
            'preparing' => 'Order Being Prepared',
            'ready' => 'Order Ready',
            'out_for_delivery' => 'Order Out for Delivery',
            'delivered' => 'Order Delivered',
            'cancelled' => 'Order Cancelled',
        ];

        $messages = [
            'confirmed' => "Your order #{$order->order_number} from {$restaurant->name} has been confirmed!",
            'preparing' => "Your order #{$order->order_number} is being prepared.",
            'ready' => "Your order #{$order->order_number} is ready!",
            'out_for_delivery' => "Your order #{$order->order_number} is out for delivery!",
            'delivered' => "Your order #{$order->order_number} has been delivered. Enjoy!",
            'cancelled' => "Your order #{$order->order_number} has been cancelled.",
        ];

        // Create in-app notification
        $this->createNotification(
            $user->id,
            'order_update',
            $titles[$status] ?? 'Order Update',
            $messages[$status] ?? "Order status updated",
            ['order_id' => $order->id, 'status' => $status]
        );

        // Send SMS notification
        if ($user->phone) {
            $this->twilioService->sendOrderStatusUpdate(
                $user->phone,
                $order->order_number,
                $status
            );
        }

        // Notify restaurant owner
        if ($status === 'pending') {
            $this->createNotification(
                $restaurant->owner_id,
                'order_update',
                'New Order Received',
                "New order #{$order->order_number} received!",
                ['order_id' => $order->id]
            );
        }

        // Notify driver
        if ($order->driver_id && in_array($status, ['ready', 'out_for_delivery'])) {
            // Notify assigned driver
            $this->createNotification(
                $order->driver_id,
                'order_update',
                'Delivery Assignment',
                "Order #{$order->order_number} assigned to you.",
                ['order_id' => $order->id]
            );

            // Notify admins about the assignment
            $adminIds = User::where('role', 'admin')->pluck('id');
            foreach ($adminIds as $adminId) {
                $this->createNotification(
                    $adminId,
                    'admin_alert',
                    'Driver Accepted Delivery',
                    sprintf(
                        '%s accepted order #%s for %s.',
                        $order->driver?->name ?? 'A driver',
                        $order->order_number,
                        $restaurant->name
                    ),
                    [
                        'order_id' => $order->id,
                        'driver_id' => $order->driver_id,
                        'status' => $status,
                    ]
                );
            }
        }
    }

    /**
     * Send promotional notification
     */
    public function sendPromotion($userIds, $title, $message, $data = [])
    {
        foreach ($userIds as $userId) {
            $this->createNotification($userId, 'promotion', $title, $message, $data);
            
            $user = User::find($userId);
            if ($user && $user->phone) {
                $this->twilioService->sendPromotion($user->phone, $message);
            }
        }
    }

    /**
     * Send system notification
     */
    public function sendSystemNotification($userIds, $title, $message, $data = [])
    {
        foreach ($userIds as $userId) {
            $this->createNotification($userId, 'system', $title, $message, $data);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId)
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($userId)
    {
        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }
}
