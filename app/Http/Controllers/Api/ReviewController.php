<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Order;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Create a review
     */
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'restaurant_rating' => 'required|integer|min:1|max:5',
            'food_rating' => 'required|integer|min:1|max:5',
            'delivery_rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'images' => 'nullable|array',
        ]);

        $order = Order::findOrFail($request->order_id);

        // Check if user owns the order
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Check if order is delivered
        if (!$order->isDelivered()) {
            return response()->json([
                'success' => false,
                'message' => 'Can only review delivered orders',
            ], 400);
        }

        // Check if review already exists
        if ($order->review) {
            return response()->json([
                'success' => false,
                'message' => 'Review already exists for this order',
            ], 400);
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'order_id' => $order->id,
            'restaurant_id' => $order->restaurant_id,
            'driver_id' => $order->driver_id,
            'restaurant_rating' => $request->restaurant_rating,
            'food_rating' => $request->food_rating,
            'delivery_rating' => $request->delivery_rating,
            'comment' => $request->comment,
            'images' => $request->images,
            'is_approved' => true,
        ]);

        // Update restaurant rating
        $order->restaurant->updateRating();

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'data' => $review->load(['user', 'restaurant']),
        ], 201);
    }

    /**
     * Get restaurant reviews
     */
    public function restaurantReviews($restaurantId)
    {
        $reviews = Review::with(['user', 'order'])
            ->where('restaurant_id', $restaurantId)
            ->approved()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }

    /**
     * Get user's reviews
     */
    public function myReviews(Request $request)
    {
        $reviews = Review::with(['restaurant', 'order'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }
}
