<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\ChatOrderService;
use Illuminate\Http\Request;

class ChatOrderController extends Controller
{
    public function __construct(protected ChatOrderService $service)
    {
    }

    public function parse(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|min:3',
        ]);

        $result = $this->service->parse($validated['message']);

        $itemsPayload = $result['items']->map(function (array $itemData) {
            /** @var \App\Models\MenuItem $menuItem */
            $menuItem = $itemData['menu_item'];

            return [
                'itemId' => $menuItem->id,
                'itemName' => $menuItem->name,
                'quantity' => $itemData['quantity'],
                'price' => $menuItem->getFinalPrice(),
            ];
        })->values();

        $restaurantPayload = $result['restaurant'] ? [
            'id' => $result['restaurant']->id,
            'name' => $result['restaurant']->name,
        ] : null;

        $priceBreakdown = $itemsPayload->map(function ($entry) {
            return $entry['price'] * $entry['quantity'];
        })->sum();

        $response = [
            'success' => true,
            'message' => $itemsPayload->isEmpty()
                ? 'I could not identify any menu items. Please clarify your order.'
                : 'I found these items. Do you want to add this order to your cart?',
            'order' => [
                'items' => $itemsPayload,
                'priceBreakdown' => $priceBreakdown,
                'restaurant' => $restaurantPayload,
            ],
            'notes' => $result['notes'],
        ];

        return response()->json($response);
    }

    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.itemId' => 'required|string|exists:menu_items,_id',
            'items.*.quantity' => 'required|integer|min:1',
            'restaurant.id' => 'nullable|string|exists:restaurants,_id',
            'restaurant.name' => 'nullable|string',
        ]);

        $items = collect($validated['items']);

        $restaurant = null;
        if (isset($validated['restaurant']['id'])) {
            $restaurant = Restaurant::find($validated['restaurant']['id']);
        }

        $cartItems = $items->map(function ($item) use ($restaurant) {
            $menuItem = MenuItem::findOrFail($item['itemId']);

            return [
                'menu_item' => $menuItem,
                'quantity' => $item['quantity'],
                'restaurant_id' => $restaurant?->id ?? $menuItem->restaurant_id,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Items ready to be added to cart via existing APIs.',
            'cart_items' => $cartItems->map(fn ($entry) => [
                'id' => $entry['menu_item']->id,
                'name' => $entry['menu_item']->name,
                'quantity' => $entry['quantity'],
                'price' => $entry['menu_item']->getFinalPrice(),
                'restaurant_id' => $entry['restaurant_id'],
            ]),
        ]);
    }
}
