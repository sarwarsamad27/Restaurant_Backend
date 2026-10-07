<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MealRecommendationService;
use Illuminate\Http\Request;

class MealRecommendationController extends Controller
{
    public function __construct(protected MealRecommendationService $service)
    {
    }

    public function recommend(Request $request)
    {
        $validated = $request->validate([
            'goal' => 'required|string|in:Weight Loss,Muscle Gain,Maintenance',
            'daily_calories' => 'required|integer|min:800|max:6000',
            'dietary_restrictions' => 'nullable|array',
            'dietary_restrictions.*' => 'string',
        ]);

        $recommendations = $this->service->recommend($validated, $request->user()->id);
        $summary = $this->service->buildSummary($recommendations, $validated);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'meals' => $recommendations->map(function ($data) {
                $item = $data['item'];

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'image_url' => $item->image_url,
                    'restaurant' => [
                        'id' => $item->restaurant?->id,
                        'name' => $item->restaurant?->name,
                    ],
                    'macros' => [
                        'calories' => $item->calories,
                        'protein' => $item->protein,
                        'carbs' => $item->carbs,
                        'fats' => $item->fats,
                    ],
                    'price' => $item->getFinalPrice(),
                    'score' => $data['score'],
                ];
            }),
        ]);
    }
}
