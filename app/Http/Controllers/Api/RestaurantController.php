<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RestaurantController extends Controller
{
    /**
     * Get all restaurants
     */
    public function index(Request $request)
    {
        $query = Restaurant::with(['owner', 'menuItems'])->active();

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by featured
        if ($request->has('featured')) {
            $query->featured();
        }

        // Filter by nearby location
        if ($request->has('latitude') && $request->has('longitude')) {
            $radius = $request->get('radius', 10); // Default 10km
            $query->nearby($request->latitude, $request->longitude, $radius);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if ($sortBy === 'rating') {
            $query->orderBy('rating', $sortOrder);
        } elseif ($sortBy === 'delivery_time') {
            $query->orderBy('delivery_time', 'asc');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 15);
        $restaurants = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $restaurants,
        ]);
    }

    /**
     * Get single restaurant
     */
    public function show($id)
    {
        $restaurant = Restaurant::with(['owner', 'menuItems.category', 'reviews.user'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $restaurant,
        ]);
    }

    /**
     * Get restaurant by slug
     */
    public function showBySlug($slug)
    {
        $restaurant = Restaurant::with(['owner', 'menuItems.category', 'reviews.user'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $restaurant,
        ]);
    }

    /**
     * Get restaurant menu
     */
    public function menu($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $menuItems = $restaurant->menuItems()
            ->with('category')
            ->available()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category.name');

        return response()->json([
            'success' => true,
            'data' => [
                'restaurant' => $restaurant,
                'menu' => $menuItems,
            ],
        ]);
    }

    /**
     * Create restaurant (Owner only)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'opening_hours' => 'required|array',
            'delivery_fee' => 'nullable|numeric|min:0',
            'delivery_time' => 'nullable|integer|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'cover_image' => 'nullable|image|max:4096',
        ]);

        $restaurant = Restaurant::create([
            'owner_id' => $request->user()->id,
            'name' => $request->name,
            'description' => $request->description,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'opening_hours' => $request->opening_hours,
            'delivery_fee' => $request->delivery_fee ?? 0,
            'delivery_time' => $request->delivery_time ?? 30,
            'minimum_order' => $request->minimum_order ?? 0,
            'status' => 'active',
            'image' => $request->hasFile('image')
                ? $request->file('image')->store('restaurants', 'public')
                : null,
            'cover_image' => $request->hasFile('cover_image')
                ? $request->file('cover_image')->store('restaurants', 'public')
                : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Restaurant created successfully',
            'data' => $restaurant,
        ], 201);
    }

    /**
     * Update restaurant
     */
    public function update(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);

        // Check if user owns the restaurant or is admin
        if ($restaurant->owner_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email',
            'address' => 'sometimes|string',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'opening_hours' => 'sometimes|array',
            'delivery_fee' => 'nullable|numeric|min:0',
            'delivery_time' => 'nullable|integer|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,inactive,closed',
            'image' => 'nullable|image|max:2048',
            'cover_image' => 'nullable|image|max:4096',
        ]);

        $updates = $request->only([
            'name',
            'description',
            'phone',
            'email',
            'address',
            'latitude',
            'longitude',
            'opening_hours',
            'delivery_fee',
            'delivery_time',
            'minimum_order',
            'status',
        ]);

        if ($request->hasFile('image')) {
            if ($restaurant->image) {
                Storage::disk('public')->delete($restaurant->image);
            }
            $updates['image'] = $request->file('image')->store('restaurants', 'public');
        }

        if ($request->hasFile('cover_image')) {
            if ($restaurant->cover_image) {
                Storage::disk('public')->delete($restaurant->cover_image);
            }
            $updates['cover_image'] = $request->file('cover_image')->store('restaurants', 'public');
        }

        $restaurant->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Restaurant updated successfully',
            'data' => $restaurant,
        ]);
    }

    /**
     * Delete restaurant
     */
    public function destroy(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);

        // Check if user owns the restaurant or is admin
        if ($restaurant->owner_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $restaurant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Restaurant deleted successfully',
        ]);
    }

    /**
     * Get featured restaurants
     */
    public function featured()
    {
        $restaurants = Restaurant::with(['owner', 'menuItems'])
            ->active()
            ->featured()
            ->orderBy('rating', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $restaurants,
        ]);
    }
}
