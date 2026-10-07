<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminDietMenuController extends Controller
{
    public function index()
    {
        $items = MenuItem::query()
            ->with('restaurant:id,name')
            ->select([
                'id',
                'restaurant_id',
                'name',
                'calories',
                'protein',
                'carbs',
                'fats',
                'is_weight_loss',
                'is_weight_gain',
                'is_maintenance',
            ])
            ->orderBy('restaurant_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function update(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:menu_items,_id',
            'items.*.calories' => 'nullable|integer|min:0|max:20000',
            'items.*.protein' => 'nullable|integer|min:0|max:2000',
            'items.*.carbs' => 'nullable|integer|min:0|max:2000',
            'items.*.fats' => 'nullable|integer|min:0|max:2000',
            'items.*.is_weight_loss' => 'required|boolean',
            'items.*.is_weight_gain' => 'required|boolean',
            'items.*.is_maintenance' => 'required|boolean',
        ])->validate();

        $payload = collect($validated['items'])
            ->keyBy('id');

        $items = MenuItem::query()
            ->whereIn('id', $payload->keys())
            ->get();

        $items->each(function (MenuItem $item) use ($payload) {
            $data = $payload[$item->id];
            $item->fill([
                'calories' => $data['calories'] ?? null,
                'protein' => $data['protein'] ?? null,
                'carbs' => $data['carbs'] ?? null,
                'fats' => $data['fats'] ?? null,
                'is_weight_loss' => (bool) $data['is_weight_loss'],
                'is_weight_gain' => (bool) $data['is_weight_gain'],
                'is_maintenance' => (bool) $data['is_maintenance'],
            ]);
            $item->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Diet menu preferences updated successfully.',
        ]);
    }
}
