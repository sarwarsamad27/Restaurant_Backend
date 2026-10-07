<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\MenuItemController as AdminMenuItemController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\MealRecommendationController;
use App\Http\Controllers\Api\ChatOrderController;
use App\Http\Controllers\Api\RestaurantRatingController;
use App\Http\Controllers\Api\Admin\AdminDietMenuController;
use App\Http\Controllers\Admin\RestaurantController as AdminRestaurantController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public restaurant routes
Route::get('/restaurants', [RestaurantController::class, 'index']);
Route::get('/restaurants/featured', [RestaurantController::class, 'featured']);
Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);
Route::get('/restaurants/slug/{slug}', [RestaurantController::class, 'showBySlug']);
Route::get('/restaurants/{id}/menu', [RestaurantController::class, 'menu']);
Route::get('/restaurant-reviews/{restaurant}', [RestaurantRatingController::class, 'publicReviews']);

// Public categories route
Route::get('/categories', [CategoryController::class, 'index']);

// Public menu items routes
Route::get('/menu-items', [MenuItemController::class, 'index']);
Route::get('/menu-items/{id}', [MenuItemController::class, 'show']);

// Protected routes for authenticated users (no rate limiting for authenticated users)
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Restaurant routes (Owner/Admin)
    Route::post('/restaurants', [RestaurantController::class, 'store']);
    Route::put('/restaurants/{id}', [RestaurantController::class, 'update']);
    Route::delete('/restaurants/{id}', [RestaurantController::class, 'destroy']);

    // Menu Item routes (Owner/Admin)
    Route::post('/menu-items', [MenuItemController::class, 'store']);
    Route::put('/menu-items/{id}', [MenuItemController::class, 'update']);
    Route::delete('/menu-items/{id}', [MenuItemController::class, 'destroy']);
    Route::post('/menu-items/{id}/toggle-availability', [MenuItemController::class, 'toggleAvailability']);

    // Order routes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/active', [OrderController::class, 'active']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{id}/assign-driver', [OrderController::class, 'assignDriver']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::get('/orders/{id}/track', [OrderController::class, 'track']);

    // Payment routes
    Route::post('/payments/stripe/intent', [PaymentController::class, 'createStripeIntent']);
    Route::post('/payments/stripe/confirm', [PaymentController::class, 'confirmStripePayment']);
    Route::post('/payments/paypal/create', [PaymentController::class, 'createPayPalOrder']);
    Route::post('/payments/paypal/capture', [PaymentController::class, 'capturePayPalOrder']);

    // Driver routes
    Route::get('/driver/me', [DriverController::class, 'me']);
    Route::post('/driver/profile', [DriverController::class, 'updateProfile']);
    Route::post('/driver/location', [DriverController::class, 'updateLocation']);
    Route::post('/driver/status', [DriverController::class, 'updateStatus']);
    Route::get('/driver/deliveries/available', [DriverController::class, 'availableDeliveries']);
    Route::get('/driver/deliveries', [DriverController::class, 'deliveries']);
    Route::post('/driver/deliveries/{orderId}/accept', [DriverController::class, 'acceptDelivery']);
    Route::post('/driver/deliveries/{orderId}/complete', [DriverController::class, 'completeDelivery']);
    Route::get('/driver/earnings', [DriverController::class, 'earnings']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Review routes
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/reviews/restaurant/{restaurantId}', [ReviewController::class, 'restaurantReviews']);
    Route::get('/reviews/my-reviews', [ReviewController::class, 'myReviews']);

    // Community ratings
    Route::post('/submit-rating', [RestaurantRatingController::class, 'store']);
    Route::get('/restaurant-ratings/{restaurant}', [RestaurantRatingController::class, 'show']);

    // AI Meal Recommendations
    Route::post('/recommend-meals', [MealRecommendationController::class, 'recommend']);

    // AI Chat Ordering
    Route::post('/ai-order', [ChatOrderController::class, 'parse']);
    Route::post('/ai-order/confirm', [ChatOrderController::class, 'confirm']);

    // Admin routes with admin middleware (rate limiting handled in admin middleware)
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        
        // User management
        Route::get('/users', [AdminController::class, 'users']);
        
        // Restaurant management
        Route::apiResource('restaurants', AdminRestaurantController::class);
        Route::get('restaurant-owners', [AdminRestaurantController::class, 'getOwners']);
        Route::put('/users/{id}/status', [AdminController::class, 'updateUserStatus']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
        Route::put('/restaurants/{id}/status', [AdminRestaurantController::class, 'updateStatus']);
        Route::post('/restaurants/{id}/toggle-featured', [AdminRestaurantController::class, 'toggleFeatured']);
        Route::get('/restaurants/{restaurant}/menu-items', [AdminMenuItemController::class, 'index']);
        Route::post('/restaurants/{restaurant}/menu-items', [AdminMenuItemController::class, 'store']);
        Route::get('/restaurants/{restaurant}/menu-items/{menuItem}', [AdminMenuItemController::class, 'show']);
        Route::put('/restaurants/{restaurant}/menu-items/{menuItem}', [AdminMenuItemController::class, 'update']);
        Route::delete('/restaurants/{restaurant}/menu-items/{menuItem}', [AdminMenuItemController::class, 'destroy']);
        Route::patch('/menu-items/{menuItem}/price', [AdminMenuItemController::class, 'updatePrice']);

        // Diet-based menu management
        Route::get('/diet-menu', [AdminDietMenuController::class, 'index']);
        Route::post('/diet-menu/update', [AdminDietMenuController::class, 'update']);

        // Restaurant ratings
        Route::get('/restaurant-ratings/{restaurant}', [RestaurantRatingController::class, 'adminIndex']);

        // Order management
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
        Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy']);

        // Menu item management
        Route::patch('/menu-items/{menuItem}/price', [AdminMenuItemController::class, 'updatePrice']);

        // Driver management
        Route::get('/drivers', [DriverController::class, 'index']);
        Route::post('/drivers/{id}/verify', [AdminController::class, 'verifyDriver']);
        
        // Notifications
        Route::post('/send-promotion', [AdminController::class, 'sendPromotion']);
        
        // Analytics
        Route::get('/analytics/revenue', [AdminController::class, 'revenueAnalytics']);
    });
});

// Webhook routes (no auth required)
Route::post('/webhooks/stripe', [PaymentController::class, 'stripeWebhook']);
Route::post('/webhooks/paypal', [PaymentController::class, 'paypalWebhook']);
