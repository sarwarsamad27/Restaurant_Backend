<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MenuItemController extends Controller
{
    /**
     * Get all menu items
     */
    public function index(Request $request)
    {
        $query = MenuItem::with(['restaurant', 'category'])->available();

        // Filter by restaurant
        if ($request->has('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by dietary preferences
        if ($request->has('vegetarian')) {
            $query->vegetarian();
        }

        if ($request->has('vegan') && $request->vegan) {
            $query->where('is_vegan', true);
        }

        // Filter by featured
        if ($request->has('featured')) {
            $query->featured();
        }

        // Sort
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 20);
        $menuItems = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $menuItems,
        ]);
    }

    /**
     * Get single menu item
     */
    public function show($id)
    {
        $menuItem = MenuItem::with(['restaurant', 'category'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $menuItem,
        ]);
    }

    /**
     * Create menu item
     */
    public function store(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'image' => 'nullable|image|max:2048',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'is_spicy' => 'boolean',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'preparation_time' => 'nullable|integer|min:0',
            'ingredients' => 'nullable|array',
            'allergens' => 'nullable|array',
        ]);

        $restaurant = Restaurant::findOrFail($request->restaurant_id);

        // Check authorization
        if ($restaurant->owner_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $data = $request->only([
            'restaurant_id',
            'category_id',
            'name',
            'description',
            'price',
            'discount_price',
            'is_vegetarian',
            'is_vegan',
            'is_spicy',
            'is_available',
            'is_featured',
            'preparation_time',
            'ingredients',
            'allergens',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('menu_items', 'public');
        }

        $menuItem = MenuItem::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Menu item created successfully',
            'data' => $menuItem->load(['restaurant', 'category']),
        ], 201);
    }

    /**
     * Update menu item
     */
    public function update(Request $request, $id)
    {
        $menuItem = MenuItem::findOrFail($id);

        // Check authorization
        if ($menuItem->restaurant->owner_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'is_spicy' => 'boolean',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'preparation_time' => 'nullable|integer|min:0',
            'ingredients' => 'nullable|array',
            'allergens' => 'nullable|array',
        ]);

        $updates = $request->only([
            'category_id',
            'name',
            'description',
            'price',
            'discount_price',
            'is_vegetarian',
            'is_vegan',
            'is_spicy',
            'is_available',
            'is_featured',
            'preparation_time',
            'ingredients',
            'allergens',
        ]);

        if ($request->hasFile('image')) {
            if ($menuItem->image) {
                Storage::disk('public')->delete($menuItem->image);
            }
            $updates['image'] = $request->file('image')->store('menu_items', 'public');
        }

        $menuItem->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Menu item updated successfully',
            'data' => $menuItem->fresh(['restaurant', 'category']),
        ]);
    }

    /**
     * Delete menu item
     */
    public function destroy(Request $request, $id)
    {
        $menuItem = MenuItem::findOrFail($id);

        // Check authorization
        if ($menuItem->restaurant->owner_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $menuItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu item deleted successfully',
        ]);
    }

    /**
     * Toggle availability
     */
    public function toggleAvailability(Request $request, $id)
    {
        $menuItem = MenuItem::findOrFail($id);

        // Check authorization
        if ($menuItem->restaurant->owner_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $menuItem->is_available = !$menuItem->is_available;
        $menuItem->save();

        return response()->json([
            'success' => true,
            'message' => 'Menu item availability updated',
            'data' => $menuItem,
        ]);
    }
}
