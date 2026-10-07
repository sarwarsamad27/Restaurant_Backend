<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    protected NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * Get all drivers
     */
    public function index(Request $request)
    {
        $query = Driver::with('user');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by verified
        if ($request->has('verified')) {
            $query->verified();
        }

        // Filter by available
        if ($request->has('available')) {
            $query->available();
        }

        $perPage = $request->get('per_page', 15);
        $drivers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $drivers,
        ]);
    }

    /**
     * Get deliveries that are awaiting driver assignment
     */
    public function availableDeliveries(Request $request)
    {
        if (!$request->user()->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $perPage = $request->get('per_page', 15);

        $orders = Order::with(['restaurant', 'items', 'user'])
            ->whereNull('driver_id')
            ->whereIn('status', ['ready'])
            ->orderBy('ready_at', 'asc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get driver profile
     */
    public function show($id)
    {
        $driver = Driver::with(['user', 'deliveries'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $driver,
        ]);
    }

    /**
     * Get authenticated driver profile
     */
    public function me(Request $request)
    {
        $driver = $request->user()->driverProfile()->with('deliveries')->first();

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $driver,
        ]);
    }

    /**
     * Create or update driver profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'vehicle_type' => 'required|string',
            'vehicle_number' => 'required|string',
            'license_number' => 'required|string',
        ]);

        $driver = Driver::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'vehicle_type' => $request->vehicle_type,
                'vehicle_number' => $request->vehicle_number,
                'license_number' => $request->license_number,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Driver profile updated successfully',
            'data' => $driver,
        ]);
    }

    /**
     * Update driver location
     */
    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $driver = $request->user()->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $driver->updateLocation($request->latitude, $request->longitude);

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
            'data' => $driver,
        ]);
    }

    /**
     * Update driver status
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:available,busy,offline',
        ]);

        $driver = $request->user()->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $driver->status = $request->status;
        $driver->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => $driver,
        ]);
    }

    /**
     * Get driver deliveries
     */
    public function deliveries(Request $request)
    {
        $driver = $request->user()->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $query = Order::with(['restaurant', 'user', 'items'])
            ->where('driver_id', $request->user()->id);

        $status = $request->get('status');

        if ($status === 'active') {
            $query->active();
        } elseif ($status) {
            $query->where('status', $status);
        } else {
            $query->active();
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $deliveries = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $deliveries,
        ]);
    }

    /**
     * Accept delivery
     */
    public function acceptDelivery(Request $request, $orderId)
    {
        $order = Order::with(['restaurant', 'user', 'driver'])->findOrFail($orderId);

        if (!in_array($order->status, ['ready', 'out_for_delivery'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order is not ready for pickup.',
            ], 400);
        }

        if ($order->driver_id && $order->driver_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Order already assigned to another driver',
            ], 400);
        }

        if (!$order->driver_id) {
            $order->driver_id = $request->user()->id;
            $order->save();
        }

        $order->updateStatus('out_for_delivery');

        $driver = $request->user()->driverProfile;
        $driver->markAsBusy();

        $order->refresh()->load(['restaurant', 'user', 'driver']);
        $this->notifications->sendOrderNotification($order, 'out_for_delivery');

        return response()->json([
            'success' => true,
            'message' => 'Delivery accepted successfully',
            'data' => $order,
        ]);
    }

    /**
     * Complete delivery
     */
    public function completeDelivery(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->driver_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $order->updateStatus('delivered');

        $driver = $request->user()->driverProfile;
        $driver->total_deliveries++;
        $driver->total_earnings += $order->delivery_fee;
        $driver->markAsAvailable();

        $order->refresh()->load(['restaurant', 'user', 'driver']);
        $this->notifications->sendOrderNotification($order, 'delivered');

        return response()->json([
            'success' => true,
            'message' => 'Delivery completed successfully',
            'data' => $order,
        ]);
    }

    /**
     * Get driver earnings
     */
    public function earnings(Request $request)
    {
        $driver = $request->user()->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $completedDeliveries = Order::where('driver_id', $request->user()->id)
            ->where('status', 'delivered')
            ->get();

        $totalEarnings = $completedDeliveries->sum('delivery_fee');
        $todayEarnings = $completedDeliveries->where('delivered_at', '>=', now()->startOfDay())->sum('delivery_fee');
        $weekEarnings = $completedDeliveries->where('delivered_at', '>=', now()->startOfWeek())->sum('delivery_fee');
        $monthEarnings = $completedDeliveries->where('delivered_at', '>=', now()->startOfMonth())->sum('delivery_fee');

        return response()->json([
            'success' => true,
            'data' => [
                'total_earnings' => $totalEarnings,
                'today_earnings' => $todayEarnings,
                'week_earnings' => $weekEarnings,
                'month_earnings' => $monthEarnings,
                'total_deliveries' => $completedDeliveries->count(),
                'rating' => $driver->rating,
            ],
        ]);
    }
}
