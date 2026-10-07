<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RestaurantController extends Controller
{
    public function index()
    {
        $restaurants = Restaurant::with('owner')->latest()->paginate(10);
        return response()->json(['data' => $restaurants]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'delivery_fee' => 'required|numeric|min:0',
            'minimum_order' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive,closed',
        ]);

        // Generate slug
        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(6);
        
        $restaurant = Restaurant::create($validated);
        
        return response()->json([
            'message' => 'Restaurant created successfully',
            'data' => $restaurant
        ], 201);
    }

    public function show(Restaurant $restaurant)
    {
        return response()->json(['data' => $restaurant->load('owner')]);
    }

    public function update(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'nullable|email',
            'address' => 'sometimes|required|string',
            'latitude' => 'sometimes|required|numeric',
            'longitude' => 'sometimes|required|numeric',
            'delivery_fee' => 'sometimes|required|numeric|min:0',
            'minimum_order' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|in:active,inactive,closed',
            'is_featured' => 'sometimes|boolean',
        ]);

        $restaurant->update($validated);
        
        return response()->json([
            'message' => 'Restaurant updated successfully',
            'data' => $restaurant
        ]);
    }

    public function destroy(Restaurant $restaurant)
    {
        $restaurant->delete();
        
        return response()->json([
            'message' => 'Restaurant deleted successfully'
        ]);
    }

    public function getOwners()
    {
        $owners = User::where('role', 'owner')->get(['id', 'name', 'email']);
        return response()->json(['data' => $owners]);
    }
}
