<?php

namespace App\Services;

use App\Models\MenuItem;
use Illuminate\Support\Collection;

class MealRecommendationService
{
    public function recommend(array $preferences, int $userId): Collection
    {
        $items = MenuItem::query()
            ->with(['restaurant'])
            ->where('is_available', true)
            ->get();

        $dietary = $preferences['dietary_restrictions'] ?? [];

        if (!empty($dietary)) {
            $items = $items->filter(fn (MenuItem $item) => $this->matchesDietaryRestrictions($item, $dietary));
        }

        $targetCalories = (int) ($preferences['daily_calories'] ?? 2000);
        $goal = $preferences['goal'] ?? 'Maintenance';

        return $items
            ->map(function (MenuItem $item) use ($targetCalories, $goal) {
                $score = $this->calculateScore($item, $targetCalories, $goal);

                return [
                    'item' => $item,
                    'score' => $score,
                ];
            })
            ->filter(fn ($data) => $data['score'] > 0)
            ->sortByDesc('score')
            ->values();
    }

    protected function matchesDietaryRestrictions(MenuItem $item, array $restrictions): bool
    {
        foreach ($restrictions as $restriction) {
            switch ($restriction) {
                case 'Vegan':
                    if (!$item->is_vegan) {
                        return false;
                    }
                    break;
                case 'Vegetarian':
                    if (!$item->is_vegetarian) {
                        return false;
                    }
                    break;
                case 'Gluten-Free':
                    if ($this->hasAllergen($item, 'gluten')) {
                        return false;
                    }
                    break;
                case 'Lactose-Free':
                case 'Dairy-Free':
                    if ($this->hasAllergen($item, 'dairy') || $this->hasAllergen($item, 'lactose')) {
                        return false;
                    }
                    break;
                default:
                    break;
            }
        }

        return true;
    }

    protected function hasAllergen(MenuItem $item, string $needle): bool
    {
        $allergens = $item->allergens ?? [];
        if (!is_array($allergens)) {
            return false;
        }

        $needle = strtolower($needle);

        return collect($allergens)
            ->map(fn ($value) => strtolower((string) $value))
            ->contains($needle);
    }

    protected function calculateScore(MenuItem $item, int $targetCalories, string $goal): float
    {
        $calories = $item->calories ?? 0;
        $protein = $item->protein ?? 0;
        $carbs = $item->carbs ?? 0;
        $fats = $item->fats ?? 0;

        $score = 0;

        // Base availability score
        $score += 10;

        // Calorie alignment
        $calorieDelta = abs($targetCalories / 3 - $calories);
        $score -= min($calorieDelta / 10, 10); // Deduct up to 10 points based on deviation

        // Goal specific adjustments
        switch ($goal) {
            case 'Weight Loss':
                $score += $calories < ($targetCalories / 3) ? 10 : 0;
                $score += $protein > 15 ? 5 : 0;
                $score -= $fats > 20 ? 5 : 0;
                break;
            case 'Muscle Gain':
                $score += $protein > 25 ? 15 : ($protein > 20 ? 10 : 0);
                $score += $carbs > 30 ? 5 : 0;
                break;
            default:
                $score += $protein > 18 ? 5 : 0;
                $score += $carbs > 20 ? 3 : 0;
        }

        // Macro balance bonus
        if ($protein && $carbs && $fats) {
            $totalMacros = $protein + $carbs + $fats;
            if ($totalMacros > 0) {
                $proteinRatio = $protein / $totalMacros;
                if ($proteinRatio >= 0.25 && $proteinRatio <= 0.45) {
                    $score += 5;
                }
            }
        }

        return round($score, 2);
    }

    public function buildSummary(Collection $recommendations, array $preferences): string
    {
        if ($recommendations->isEmpty()) {
            return 'We could not find meals matching your preferences right now.';
        }

        $topItem = $recommendations->first()['item'];
        $goal = $preferences['goal'] ?? 'your goals';
        $calories = $preferences['daily_calories'] ?? null;

        $summaryParts = [
            'These meals were selected to support your ' . strtolower($goal) . ' plan.'
        ];

        if ($calories) {
            $summaryParts[] = 'We aimed for meals around ' . (int)$calories . ' daily calories.';
        }

        if ($topItem->protein) {
            $summaryParts[] = 'Top pick "' . $topItem->name . '" offers ' . $topItem->protein . 'g protein';
        }

        return implode(' ', $summaryParts);
    }
}
