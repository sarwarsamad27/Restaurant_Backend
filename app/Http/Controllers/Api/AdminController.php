<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\Driver;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get dashboard statistics
     */
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_customers' => User::customers()->count(),
            'total_drivers' => User::drivers()->count(),
            'total_restaurants' => Restaurant::count(),
            'active_restaurants' => Restaurant::active()->count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::pending()->count(),
            'active_orders' => Order::active()->count(),
            'completed_orders' => Order::completed()->count(),
            'cancelled_orders' => Order::cancelled()->count(),
            'total_revenue' => round((float) Order::completed()->sum('total'), 2),
            'today_revenue' => $this->revenueBetween(now()->startOfDay(), now()->endOfDay()),
            'week_revenue' => $this->revenueBetween(now()->startOfWeek(), now()->endOfWeek()),
            'month_revenue' => $this->revenueBetween(now()->startOfMonth(), now()->endOfMonth()),
        ];

        // Recent orders
        $recentOrders = Order::with(['user', 'restaurant', 'driver'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Top restaurants by orders
        $orderCounts = Order::all(['restaurant_id'])->countBy('restaurant_id');
        $topRestaurants = Restaurant::whereIn('_id', $orderCounts->sortDesc()->keys()->take(5)->all())
            ->get()
            ->each(fn ($restaurant) => $restaurant->setAttribute('orders_count', $orderCounts->get($restaurant->id, 0)))
            ->sortByDesc('orders_count')
            ->values();

        // Orders by status
        $ordersByStatus = Order::all(['status'])
            ->countBy('status')
            ->map(fn ($count, $status) => ['status' => $status, 'count' => $count])
            ->values();

        // Revenue chart data (last 7 days)
        $revenueChart = Order::completed()
            ->whereBetween('delivered_at', [now()->subDays(7), now()])
            ->get(['delivered_at', 'total'])
            ->groupBy(fn ($order) => $order->delivered_at->toDateString())
            ->map(fn ($orders, $date) => [
                'date' => $date,
                'revenue' => round((float) $orders->sum('total'), 2),
                'orders' => $orders->count(),
            ])
            ->sortKeys()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_orders' => $recentOrders,
                'top_restaurants' => $topRestaurants,
                'orders_by_status' => $ordersByStatus,
                'revenue_chart' => $revenueChart,
            ],
        ]);
    }

    /**
     * Get all users
     */
    public function users(Request $request)
    {
        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Update user status
     */
    public function updateUserStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $user = User::findOrFail($id);
        $user->status = $request->status;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => $user,
        ]);
    }

    /**
     * Delete user
     */
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete admin user',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Get all restaurants for admin
     */
    public function restaurants(Request $request)
    {
        $query = Restaurant::with('owner');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $restaurants = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $restaurants,
        ]);
    }

    /**
     * Update restaurant status
     */
    public function updateRestaurantStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,closed',
        ]);

        $restaurant = Restaurant::findOrFail($id);
        $restaurant->status = $request->status;
        $restaurant->save();

        return response()->json([
            'success' => true,
            'message' => 'Restaurant status updated successfully',
            'data' => $restaurant,
        ]);
    }

    /**
     * Toggle restaurant featured status
     */
    public function toggleFeatured($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $restaurant->is_featured = !$restaurant->is_featured;
        $restaurant->save();

        return response()->json([
            'success' => true,
            'message' => 'Restaurant featured status updated',
            'data' => $restaurant,
        ]);
    }

    /**
     * Get all orders for admin
     */
    public function orders(Request $request)
    {
        $query = Order::with(['user', 'restaurant', 'driver', 'items']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by restaurant
        if ($request->has('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
        }

        // Search by order number
        if ($request->has('search')) {
            $query->where('order_number', 'like', '%' . $request->search . '%');
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Verify driver
     */
    public function verifyDriver(Request $request, $id)
    {
        $driver = Driver::findOrFail($id);
        $driver->is_verified = true;
        $driver->save();

        // Notify driver
        $this->notificationService->createNotification(
            $driver->user_id,
            'system',
            'Account Verified',
            'Your driver account has been verified. You can now start accepting deliveries.',
            []
        );

        return response()->json([
            'success' => true,
            'message' => 'Driver verified successfully',
            'data' => $driver,
        ]);
    }

    /**
     * Send promotional notification
     */
    public function sendPromotion(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'user_role' => 'nullable|in:customer,driver,restaurant_owner,all',
        ]);

        $query = User::where('status', 'active');

        if ($request->has('user_role') && $request->user_role !== 'all') {
            $query->where('role', $request->user_role);
        }

        $userIds = $query->get()->pluck('id')->toArray();

        $this->notificationService->sendPromotion(
            $userIds,
            $request->title,
            $request->message,
            []
        );

        return response()->json([
            'success' => true,
            'message' => 'Promotion sent successfully',
            'recipients_count' => count($userIds),
        ]);
    }

    /**
     * Get revenue analytics
     */
    public function revenueAnalytics(Request $request)
    {
        $period = $request->get('period', 'month'); // day, week, month, year

        [$from, $to] = match ($period) {
            'day' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $orders = Order::completed()
            ->whereBetween('delivered_at', [$from, $to])
            ->get(['restaurant_id', 'payment_method', 'total']);

        $totalRevenue = round((float) $orders->sum('total'), 2);
        $totalOrders = $orders->count();
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Revenue by restaurant
        $restaurants = Restaurant::whereIn('_id', $orders->pluck('restaurant_id')->unique()->values()->all())
            ->get(['name'])
            ->keyBy('id');

        $revenueByRestaurant = $orders->groupBy('restaurant_id')
            ->map(fn ($group, $restaurantId) => [
                'restaurant_id' => $restaurantId,
                'revenue' => round((float) $group->sum('total'), 2),
                'restaurant' => $restaurants->get($restaurantId)?->only(['id', 'name']),
            ])
            ->sortByDesc('revenue')
            ->take(10)
            ->values();

        // Revenue by payment method
        $revenueByPaymentMethod = $orders->groupBy('payment_method')
            ->map(fn ($group, $method) => [
                'payment_method' => $method,
                'revenue' => round((float) $group->sum('total'), 2),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'average_order_value' => round($averageOrderValue, 2),
                'revenue_by_restaurant' => $revenueByRestaurant,
                'revenue_by_payment_method' => $revenueByPaymentMethod,
            ],
        ]);
    }

    /**
     * Revenue of delivered orders inside a date range
     */
    protected function revenueBetween($from, $to): float
    {
        return round((float) Order::completed()->whereBetween('delivered_at', [$from, $to])->sum('total'), 2);
    }
}
