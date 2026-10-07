<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\MenuItem;
use App\Models\Coupon;
use App\Services\NotificationService;
use App\Services\StripeService;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles all order-related operations
 * 
 * Note: We're using repository pattern in some places, but kept it simple here
 * since the logic isn't too complex yet. Could be refactored later if needed.
 */
class OrderController extends Controller
{
    // These services handle the heavy lifting for notifications and payments
    protected $notificationService;
    protected $stripeService;
    protected $paypalService;

    public function __construct(
        NotificationService $notificationService,
        StripeService $stripeService,
        PayPalService $paypalService
    ) {
        $this->notificationService = $notificationService;
        $this->stripeService = $stripeService;
        $this->paypalService = $paypalService;
        
        // We'll add rate limiting here if needed
        // $this->middleware('throttle:60,1');
    }

    /**
     * Get user's order history
     * 
     * Supports filtering by status and pagination. Defaults to showing
     * the most recent orders first because that's what users typically want.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Order::with(['restaurant', 'items.menuItem', 'driver'])
            ->where('user_id', $request->user()->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get single order
     */
    public function show(Request $request, $id)
    {
        $order = Order::with(['restaurant', 'items.menuItem', 'driver', 'user', 'review'])
            ->findOrFail($id);

        if (!$this->canViewOrder($request->user(), $order)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Create new order
     */
    public function store(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'order_type' => 'required|in:delivery,takeaway,dine_in',
            'payment_method' => 'required|in:cash,card,stripe,paypal,online',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.customizations' => 'nullable|array',
            'items.*.special_instructions' => 'nullable|string',
            'delivery_address' => 'required_if:order_type,delivery|string',
            'delivery_latitude' => 'required_if:order_type,delivery|numeric',
            'delivery_longitude' => 'required_if:order_type,delivery|numeric',
            'customer_phone' => 'required|string',
            'special_instructions' => 'nullable|string',
            'table_number' => 'required_if:order_type,dine_in|string',
            'coupon_code' => 'nullable|string',
        ]);

        if (!$request->user()->isCustomer()) {
            return response()->json([
                'success' => false,
                'message' => 'Only customers can place orders.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $restaurant = Restaurant::findOrFail($request->restaurant_id);
            $subtotal = 0;
            $orderItems = [];

            // Calculate subtotal and prepare order items
            foreach ($request->items as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                $price = $menuItem->getFinalPrice();
                $quantity = $item['quantity'];
                $itemSubtotal = $price * $quantity;
                $subtotal += $itemSubtotal;

                $orderItems[] = [
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'customizations' => $item['customizations'] ?? null,
                    'special_instructions' => $item['special_instructions'] ?? null,
                    'subtotal' => $itemSubtotal,
                ];
            }

            // Calculate tax (10%)
            $tax = $subtotal * 0.10;

            // Calculate delivery fee
            $deliveryFee = $request->order_type === 'delivery' ? $restaurant->delivery_fee : 0;

            // Apply coupon if provided
            $discount = 0;
            if ($request->has('coupon_code')) {
                $coupon = Coupon::where('code', $request->coupon_code)->first();
                if ($coupon && $coupon->isValid($subtotal)) {
                    $discount = $coupon->calculateDiscount($subtotal);
                    $coupon->incrementUsage();
                }
            }

            // Calculate total
            $total = $subtotal + $tax + $deliveryFee - $discount;

            // Check minimum order
            if ($subtotal < $restaurant->minimum_order) {
                return response()->json([
                    'success' => false,
                    'message' => "Minimum order amount is $" . $restaurant->minimum_order,
                ], 400);
            }

            // Create order
            $order = Order::create([
                'user_id' => $request->user()->id,
                'restaurant_id' => $restaurant->id,
                'order_type' => $request->order_type,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $request->payment_method,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'delivery_fee' => $deliveryFee,
                'discount' => $discount,
                'total' => $total,
                'delivery_address' => $request->delivery_address,
                'delivery_latitude' => $request->delivery_latitude,
                'delivery_longitude' => $request->delivery_longitude,
                'customer_phone' => $request->customer_phone,
                'special_instructions' => $request->special_instructions,
                'table_number' => $request->table_number,
            ]);

            // Create order items
            foreach ($orderItems as $item) {
                $order->items()->create($item);
            }

            // Handle payment
            $paymentResult = null;
            if ($request->payment_method === 'stripe') {
                $paymentResult = $this->stripeService->createPaymentIntent(
                    $total,
                    'usd',
                    ['order_id' => $order->id]
                );
            } elseif ($request->payment_method === 'paypal') {
                $paymentResult = $this->paypalService->createOrder($total, 'USD', [
                    'description' => "Order #{$order->order_number}",
                ]);
            }

            DB::commit();

            // Send notifications
            $this->notificationService->sendOrderNotification($order, 'pending');

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order' => $order->load(['items', 'restaurant']),
                    'payment' => $paymentResult,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready,out_for_delivery,delivered,cancelled',
            'cancellation_reason' => 'required_if:status,cancelled|string',
        ]);

        $order = Order::findOrFail($id);

        // Authorization check
        $user = $request->user();
        if (!$user->isAdmin() && 
            $order->restaurant->owner_id !== $user->id && 
            $order->driver_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $order->updateStatus($request->status);

        if ($request->status === 'cancelled') {
            $order->cancellation_reason = $request->cancellation_reason;
            $order->save();
        }

        // Send notification
        $this->notificationService->sendOrderNotification($order, $request->status);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->fresh(['restaurant', 'items', 'driver']),
        ]);
    }

    /**
     * Assign driver to order
     */
    public function assignDriver(Request $request, $id)
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id',
        ]);

        $order = Order::findOrFail($id);

        // Check authorization
        if (!$request->user()->isAdmin() && 
            $order->restaurant->owner_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $order->driver_id = $request->driver_id;
        $order->save();

        // Notify driver
        $this->notificationService->createNotification(
            $request->driver_id,
            'order_update',
            'New Delivery Assignment',
            "You have been assigned to order #{$order->order_number}",
            ['order_id' => $order->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Driver assigned successfully',
            'data' => $order->fresh(['driver']),
        ]);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $order = Order::findOrFail($id);

        // Check if order can be cancelled
        if (!$order->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled at this stage',
            ], 400);
        }

        // Check authorization
        if ($order->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $order->updateStatus('cancelled');
        $order->cancellation_reason = $request->reason;
        $order->save();

        // Send notification
        $this->notificationService->sendOrderNotification($order, 'cancelled');

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data' => $order,
        ]);
    }

    /**
     * Get active orders (for restaurant/driver dashboard)
     */
    public function active(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['restaurant', 'items', 'user', 'driver'])->active();

        if ($user->isRestaurantOwner()) {
            // Get orders for user's restaurants
            $restaurantIds = $user->restaurants->pluck('id');
            $query->whereIn('restaurant_id', $restaurantIds);
        } elseif ($user->isDriver()) {
            // Get orders assigned to driver
            $query->where('driver_id', $user->id);
        } elseif (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Track order
     */
    public function track(Request $request, $id)
    {
        $order = Order::with(['restaurant', 'driver.driverProfile', 'items'])
            ->findOrFail($id);

        if (!$this->canViewOrder($request->user(), $order)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $trackingData = [
            'order' => $order,
            'timeline' => [
                'pending' => $order->created_at,
                'confirmed' => $order->confirmed_at,
                'preparing' => $order->preparing_at,
                'ready' => $order->ready_at,
                'out_for_delivery' => $order->out_for_delivery_at,
                'delivered' => $order->delivered_at,
            ],
            'driver_location' => null,
        ];

        if ($order->driver && $order->driver->driverProfile) {
            $trackingData['driver_location'] = [
                'latitude' => $order->driver->driverProfile->current_latitude,
                'longitude' => $order->driver->driverProfile->current_longitude,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $trackingData,
        ]);
    }

    /**
     * Only the customer, the restaurant owner, the assigned driver or an admin may see an order
     */
    protected function canViewOrder($user, Order $order): bool
    {
        return $user->isAdmin()
            || $order->user_id === $user->id
            || $order->driver_id === $user->id
            || $order->restaurant?->owner_id === $user->id;
    }
}
