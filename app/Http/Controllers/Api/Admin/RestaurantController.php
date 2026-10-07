<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RestaurantController extends Controller
{
    public function index(Request $request)
    {
        $query = Restaurant::with(['owner'])->withCount(['orders', 'menuItems']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 10);

        $restaurants = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $restaurants,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'owner_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'delivery_fee' => 'nullable|numeric|min:0',
            'delivery_time' => 'nullable|integer|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'opening_hours' => 'nullable|array',
            'opening_hours.*.open' => 'nullable|string',
            'opening_hours.*.close' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
            'cover_image' => 'nullable|image|max:15360',
            'status' => 'nullable|in:active,inactive,closed',
        ]);

        $owner = User::find($data['owner_id']);
        if (!$owner || !$owner->isRestaurantOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Owner must be a registered restaurant owner.',
            ], 422);
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('restaurants', 'public');
        }

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('restaurants', 'public');
        }

        $data['status'] = $data['status'] ?? 'inactive';
        $data['opening_hours'] = $data['opening_hours'] ?? null;

        $restaurant = Restaurant::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Restaurant created successfully.',
            'data' => $restaurant->load('owner'),
        ], 201);
    }

    public function show(Restaurant $restaurant)
    {
        $restaurant->load(['owner', 'menuItems' => function ($query) {
            $query->with('category')->orderBy('name');
        }]);

        return response()->json([
            'success' => true,
            'data' => $restaurant,
        ]);
    }

    public function update(Request $request, Restaurant $restaurant)
    {
        $data = $request->validate([
            'owner_id' => 'sometimes|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'address' => 'sometimes|string',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'delivery_fee' => 'nullable|numeric|min:0',
            'delivery_time' => 'nullable|integer|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'opening_hours' => 'nullable|array',
            'opening_hours.*.open' => 'nullable|string',
            'opening_hours.*.close' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
            'cover_image' => 'nullable|image|max:15360',
            'remove_image' => 'nullable|boolean',
            'remove_cover_image' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,closed',
        ]);

        if (isset($data['owner_id'])) {
            $owner = User::find($data['owner_id']);
            if (!$owner || !$owner->isRestaurantOwner()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Owner must be a registered restaurant owner.',
                ], 422);
            }
        }

        $removeImage = $request->boolean('remove_image');
        $removeCoverImage = $request->boolean('remove_cover_image');

        if ($request->hasFile('image')) {
            if ($restaurant->image) {
                Storage::disk('public')->delete($restaurant->image);
            }
            $data['image'] = $request->file('image')->store('restaurants', 'public');
        } elseif ($removeImage) {
            if ($restaurant->image) {
                Storage::disk('public')->delete($restaurant->image);
            }
            $data['image'] = null;
        }

        if ($request->hasFile('cover_image')) {
            if ($restaurant->cover_image) {
                Storage::disk('public')->delete($restaurant->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('restaurants', 'public');
        } elseif ($removeCoverImage) {
            if ($restaurant->cover_image) {
                Storage::disk('public')->delete($restaurant->cover_image);
            }
            $data['cover_image'] = null;
        }

        unset($data['remove_image'], $data['remove_cover_image']);

        $data['opening_hours'] = $data['opening_hours'] ?? $restaurant->opening_hours;

        $restaurant->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Restaurant updated successfully.',
            'data' => $restaurant->fresh(['owner', 'menuItems']),
        ]);
    }

    public function destroy(Restaurant $restaurant)
    {
        if ($restaurant->image) {
            Storage::disk('public')->delete($restaurant->image);
        }

        $restaurant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Restaurant deleted successfully.',
        ]);
    }

    public function updateStatus(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,closed',
        ]);

        $restaurant->update(['status' => $validated['status']]);

        $restaurant->load(['owner'])->loadCount(['orders', 'menuItems']);

        return response()->json([
            'success' => true,
            'message' => 'Restaurant status updated successfully.',
            'data' => $restaurant,
        ]);
    }

    public function toggleFeatured(Restaurant $restaurant)
    {
        $restaurant->is_featured = !$restaurant->is_featured;
        $restaurant->save();

        $restaurant->load(['owner'])->loadCount(['orders', 'menuItems']);

        return response()->json([
            'success' => true,
            'message' => 'Restaurant featured status updated.',
            'data' => $restaurant,
        ]);
    }
}
