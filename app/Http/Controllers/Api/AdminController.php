<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\Driver;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'total_revenue' => Order::completed()->sum('total'),
            'today_revenue' => Order::completed()
                ->whereDate('delivered_at', today())
                ->sum('total'),
            'week_revenue' => Order::completed()
                ->whereBetween('delivered_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('total'),
            'month_revenue' => Order::completed()
                ->whereMonth('delivered_at', now()->month)
                ->sum('total'),
        ];

        // Recent orders
        $recentOrders = Order::with(['user', 'restaurant', 'driver'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Top restaurants by orders
        $topRestaurants = Restaurant::withCount('orders')
            ->orderBy('orders_count', 'desc')
            ->limit(5)
            ->get();

        // Orders by status
        $ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        // Revenue chart data (last 7 days)
        $revenueChart = Order::completed()
            ->whereBetween('delivered_at', [now()->subDays(7), now()])
            ->select(
                DB::raw('DATE(delivered_at) as date'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

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
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
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

        $userIds = $query->pluck('id')->toArray();

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

        $query = Order::completed();

        switch ($period) {
            case 'day':
                $query->whereDate('delivered_at', today());
                break;
            case 'week':
                $query->whereBetween('delivered_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('delivered_at', now()->month);
                break;
            case 'year':
                $query->whereYear('delivered_at', now()->year);
                break;
        }

        $totalRevenue = $query->sum('total');
        $totalOrders = $query->count();
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Revenue by restaurant
        $revenueByRestaurant = $query->select('restaurant_id', DB::raw('SUM(total) as revenue'))
            ->with('restaurant:id,name')
            ->groupBy('restaurant_id')
            ->orderBy('revenue', 'desc')
            ->limit(10)
            ->get();

        // Revenue by payment method
        $revenueByPaymentMethod = $query->select('payment_method', DB::raw('SUM(total) as revenue'))
            ->groupBy('payment_method')
            ->get();

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
}
