<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    public function index(Request $request)
    {
        $query = Order::with([
            'user',
            'restaurant',
            'items.menuItem',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('restaurant', function ($restaurantQuery) use ($search) {
                        $restaurantQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->integer('per_page', 15);

        $orders = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function show($id)
    {
        $order = Order::with([
            'user',
            'restaurant',
            'driver',
            'items.menuItem',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready,out_for_delivery,delivered,cancelled',
            'cancellation_reason' => 'nullable|string|max:255',
        ]);

        $order = Order::with(['user', 'restaurant', 'driver'])->findOrFail($id);

        $statusMap = [
            'pending' => 'pending',
            'confirmed' => 'confirmed',
            'preparing' => 'preparing',
            'ready' => 'ready',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
        ];

        $nextStatus = $statusMap[$validated['status']];

        if ($nextStatus === 'cancelled' && empty($validated['cancellation_reason'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation reason is required.',
            ], 422);
        }

        $assignedDriver = null;

        if (in_array($nextStatus, ['ready', 'out_for_delivery']) && !$order->driver_id) {
            $assignedDriver = Driver::available()
                ->with('user')
                ->orderBy('total_deliveries')
                ->orderBy('id')
                ->first();

            if ($assignedDriver) {
                $order->driver_id = $assignedDriver->user_id;
                $order->save();
                $assignedDriver->markAsBusy();
            }
        }

        $order->updateStatus($nextStatus);

        if ($nextStatus === 'cancelled') {
            $order->cancellation_reason = $validated['cancellation_reason'];
            $order->save();

            if ($order->driver_id) {
                $driver = Driver::where('user_id', $order->driver_id)->first();
                if ($driver) {
                    $driver->markAsAvailable();
                }
            }
        }

        if (in_array($nextStatus, ['pending', 'confirmed', 'preparing']) && $order->driver_id) {
            $driver = Driver::where('user_id', $order->driver_id)->first();
            if ($driver && $driver->status !== 'busy') {
                $driver->markAsAvailable();
            }
        }

        if ($nextStatus === 'out_for_delivery' && $order->driver_id) {
            $driver = Driver::where('user_id', $order->driver_id)->first();
            if ($driver) {
                $driver->markAsBusy();
            }
        }

        if ($nextStatus === 'delivered' && $order->driver_id) {
            $driver = Driver::where('user_id', $order->driver_id)->first();
            if ($driver) {
                $driver->total_deliveries++;
                $driver->total_earnings += $order->delivery_fee ?? 0;
                $driver->markAsAvailable();
            }
        }

        $order->load(['user', 'restaurant', 'driver']);

        $this->notifications->sendOrderNotification($order, $nextStatus);

        $message = 'Order status updated successfully.';
        if (in_array($nextStatus, ['ready', 'out_for_delivery'])) {
            if ($order->driver) {
                $message = sprintf(
                    'Order marked as %s and assigned to %s.',
                    str_replace('_', ' ', $nextStatus),
                    $order->driver->name ?? 'driver'
                );
            } else {
                $message = sprintf(
                    'Order marked as %s but no available drivers were found.',
                    str_replace('_', ' ', $nextStatus)
                );
            }
        }

        if ($nextStatus === 'cancelled') {
            $message = 'Order cancelled successfully.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $order,
        ]);
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order archived successfully.',
        ]);
    }
}
