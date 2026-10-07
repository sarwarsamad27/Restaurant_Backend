<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
    public function index(Restaurant $restaurant)
    {
        $menuItems = $restaurant->menuItems()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $menuItems,
        ]);
    }

    public function store(Request $request, Restaurant $restaurant)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'is_available' => 'required|boolean',
            'is_featured' => 'nullable|boolean',
            'preparation_time' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'image' => 'nullable|image|max:10240',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('menu-items', 'public');
        }

        $data['restaurant_id'] = $restaurant->id;
        $data['is_available'] = (bool) $data['is_available'];

        $menuItem = MenuItem::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Menu item created successfully.',
            'data' => $menuItem->fresh(['restaurant', 'category']),
        ], 201);
    }

    public function update(Request $request, Restaurant $restaurant, MenuItem $menuItem)
    {
        $this->ensureMenuItemBelongsToRestaurant($menuItem, $restaurant);

        $data = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'is_available' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'preparation_time' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'image' => 'nullable|image|max:10240',
            'remove_image' => 'nullable|boolean',
        ]);

        if (array_key_exists('discount_price', $data) && !array_key_exists('price', $data)) {
            $data['price'] = $menuItem->price;
        }

        $removeImage = $request->boolean('remove_image');

        if ($request->hasFile('image')) {
            if ($menuItem->image) {
                Storage::disk('public')->delete($menuItem->image);
            }
            $data['image'] = $request->file('image')->store('menu-items', 'public');
        } elseif ($removeImage) {
            if ($menuItem->image) {
                Storage::disk('public')->delete($menuItem->image);
            }
            $data['image'] = null;
        }

        unset($data['remove_image']);

        $menuItem->fill($data);

        if (isset($data['name'])) {
            $menuItem->slug = Str::slug($data['name']);
        }

        $menuItem->save();

        return response()->json([
            'success' => true,
            'message' => 'Menu item updated successfully.',
            'data' => $menuItem->fresh(['restaurant', 'category']),
        ]);
    }

    public function destroy(Restaurant $restaurant, MenuItem $menuItem)
    {
        $this->ensureMenuItemBelongsToRestaurant($menuItem, $restaurant);

        if ($menuItem->image) {
            Storage::disk('public')->delete($menuItem->image);
        }

        $menuItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu item deleted successfully.',
        ]);
    }

    public function updatePrice(Request $request, MenuItem $menuItem)
    {
        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'is_available' => 'nullable|boolean',
        ]);

        $menuItem->price = $data['price'];
        $menuItem->discount_price = $data['discount_price'] ?? null;

        if (array_key_exists('is_available', $data)) {
            $menuItem->is_available = $data['is_available'];
        }

        $menuItem->save();

        return response()->json([
            'success' => true,
            'message' => 'Menu item price updated successfully.',
            'data' => $menuItem->fresh(['restaurant', 'category']),
        ]);
    }

    protected function ensureMenuItemBelongsToRestaurant(MenuItem $menuItem, Restaurant $restaurant): void
    {
        if ($menuItem->restaurant_id !== $restaurant->id) {
            abort(404, 'Menu item not found for the specified restaurant.');
        }
    }
}
