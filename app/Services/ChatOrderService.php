<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatOrderService
{
    protected array $numberWords = [
        'zero' => 0,
        'one' => 1,
        'two' => 2,
        'three' => 3,
        'four' => 4,
        'five' => 5,
        'six' => 6,
        'seven' => 7,
        'eight' => 8,
        'nine' => 9,
        'ten' => 10,
    ];

    public function parse(string $message): array
    {
        $normalizedMessage = $this->normalizeMessage($message);
        $restaurant = $this->detectRestaurant($normalizedMessage);

        $itemsQuery = MenuItem::query()
            ->with('restaurant')
            ->where('is_available', true);

        if ($restaurant) {
            $itemsQuery->where('restaurant_id', $restaurant->id);
        }

        $menuItems = $itemsQuery->get();
        $detectedItems = collect();
        $bestMatch = null;
        $highestConfidence = 0;

        // First pass: find the best matching item
        foreach ($menuItems as $item) {
            $match = $this->matchMenuItem($item, $normalizedMessage);
            
            if ($match && $match['match_confidence'] > $highestConfidence) {
                $bestMatch = $match;
                $highestConfidence = $match['match_confidence'];
            }
        }

        // If we found a good match, use it
        if ($bestMatch && $highestConfidence >= 0.8) {
            $detectedItems->push($bestMatch);
            if (!$restaurant) {
                $restaurant = $bestMatch['menu_item']->restaurant;
            }
        } else {
            // Fall back to the original behavior if no good match found
            foreach ($menuItems as $item) {
                $match = $this->matchMenuItem($item, $normalizedMessage);
                if ($match) {
                    $detectedItems->push($match);
                    if (!$restaurant) {
                        $restaurant = $item->restaurant;
                    }
                }
            }
        }

        if ($detectedItems->isEmpty()) {
            return [
                'items' => collect(),
                'restaurant' => $restaurant,
                'notes' => ['We could not find any menu items in your message. Try mentioning the exact item names.'],
            ];
        }

        // Group duplicate items and sum quantities
        $grouped = $detectedItems->groupBy(fn ($entry) => $entry['menu_item']->id)
            ->map(function (Collection $group) {
                /** @var MenuItem $menuItem */
                $menuItem = $group->first()['menu_item'];
                $quantity = $group->sum('quantity');

                return [
                    'menu_item' => $menuItem,
                    'quantity' => $quantity,
                    'matched_text' => $group->pluck('matched_text')->implode(', '),
                ];
            })
            ->values();

        return [
            'items' => $grouped,
            'restaurant' => $restaurant,
            'notes' => [],
        ];
    }

    protected function normalizeMessage(string $message): string
    {
        $normalized = Str::lower($message);

        foreach ($this->numberWords as $word => $number) {
            $normalized = preg_replace('/\b' . preg_quote($word, '/') . '\b/', (string) $number, $normalized);
        }

        return $normalized;
    }

    protected function detectRestaurant(string $message): ?Restaurant
    {
        $restaurants = Restaurant::query()
            ->select(['id', 'name', 'delivery_fee'])
            ->get();

        foreach ($restaurants as $restaurant) {
            if (Str::contains($message, Str::lower($restaurant->name))) {
                return $restaurant;
            }
        }

        return null;
    }

    protected function matchMenuItem(MenuItem $menuItem, string $message): ?array
    {
        $name = Str::lower($menuItem->name);
        $normalizedMessage = Str::lower($message);
        $messageWords = array_filter(preg_split('/\s+/', $normalizedMessage), function($word) {
            return strlen($word) > 2; // Only consider words longer than 2 characters
        });
        $menuItemWords = array_filter(preg_split('/\s+/', $name), function($word) {
            return strlen($word) > 2;
        });

        // Check for exact match first (case insensitive)
        if (str_contains($normalizedMessage, $name)) {
            $quantity = $this->extractQuantity($normalizedMessage, $name);
            return [
                'menu_item' => $menuItem,
                'quantity' => $quantity,
                'matched_text' => $name,
                'match_confidence' => 1.0, // Highest confidence for exact match
            ];
        }

        // Check if the menu item contains all search terms
        $allTermsMatch = true;
        $matchedTerms = [];
        
        foreach ($messageWords as $term) {
            $termMatched = false;
            foreach ($menuItemWords as $menuWord) {
                if (str_contains($menuWord, $term) || 
                    similar_text($menuWord, $term) / max(strlen($menuWord), strlen($term)) > 0.8) {
                    $termMatched = true;
                    $matchedTerms[] = $term;
                    break;
                }
            }
            if (!$termMatched) {
                $allTermsMatch = false;
            }
        }

        if ($allTermsMatch && !empty($messageWords)) {
            $quantity = $this->extractQuantity($normalizedMessage, implode(' ', $matchedTerms));
            $confidence = 0.9; // High confidence for matching all terms
            
            // Increase confidence if the match is at the start of the name
            if (Str::startsWith($name, $matchedTerms[0])) {
                $confidence = 0.95;
            }
            
            return [
                'menu_item' => $menuItem,
                'quantity' => $quantity,
                'matched_text' => implode(' ', $matchedTerms),
                'match_confidence' => $confidence,
            ];
        }

        // Check if all search terms are present in the menu item name
        $searchTerms = array_filter($messageWords, function($word) {
            return strlen($word) > 2; // Only consider words longer than 2 characters
        });

        $matchedTerms = [];
        foreach ($searchTerms as $term) {
            foreach ($menuItemWords as $menuWord) {
                if (str_contains($menuWord, $term) || 
                    similar_text($menuWord, $term) / max(strlen($menuWord), strlen($term)) > 0.7) {
                    $matchedTerms[] = $term;
                    break;
                }
            }
        }

        // If all search terms are found in the menu item
        if (count($matchedTerms) === count($searchTerms) && !empty($searchTerms)) {
            $quantity = $this->extractQuantity($normalizedMessage, implode(' ', $matchedTerms));
            return [
                'menu_item' => $menuItem,
                'quantity' => $quantity,
                'matched_text' => implode(' ', $matchedTerms),
                'match_confidence' => 0.9, // High confidence for matching all terms
            ];
        }

        // Split the menu item name into words
        $nameWords = preg_split('/\s+/', $name);
        $messageWords = preg_split('/\s+/', $normalizedMessage);

        // Calculate match score based on word matches
        $matchScore = 0;
        $matchedWords = [];

        foreach ($nameWords as $word) {
            if (strlen($word) <= 2) {
                // Skip very short words as they can cause false positives
                continue;
            }

            foreach ($messageWords as $msgWord) {
                if (str_contains($msgWord, $word) ||
                    similar_text($msgWord, $word) / strlen($word) > 0.7) {
                    $matchScore++;
                    $matchedWords[] = $word;
                    break;
                }
            }
        }

        // If we matched at least half of the words or 70% of the characters
        $minWordsMatch = max(1, count($nameWords) * 0.5);
        $minLengthMatch = strlen($name) * 0.7;
        $currentLengthMatch = strlen(implode('', $matchedWords));

        if ($matchScore >= $minWordsMatch || $currentLengthMatch >= $minLengthMatch) {
            $quantity = $this->extractQuantity($normalizedMessage, implode(' ', $matchedWords));
            return [
                'menu_item' => $menuItem,
                'quantity' => $quantity,
                'matched_text' => implode(' ', $matchedWords),
                'match_confidence' => min(0.9, $matchScore / count($nameWords)),
            ];
        }

        // Check for partial string match (at least 4 characters)
        if (strlen($name) >= 4) {
            foreach (explode(' ', $normalizedMessage) as $word) {
                if (strlen($word) < 4) continue;

                if (str_contains($name, $word) ||
                    similar_text($name, $word) / max(strlen($name), strlen($word)) > 0.7) {
                    $quantity = $this->extractQuantity($normalizedMessage, $word);
                    return [
                        'menu_item' => $menuItem,
                        'quantity' => $quantity,
                        'matched_text' => $word,
                        'match_confidence' => 0.7,
                    ];
                }
            }
        }

        return null;
    }

    protected function generateNameVariants(MenuItem $menuItem): array
    {
        $base = Str::lower($menuItem->name);
        $variants = [$base];

        $slug = Str::slug($menuItem->name, ' ');
        if ($slug !== $base) {
            $variants[] = $slug;
        }

        $singular = Str::singular($base);
        $plural = Str::plural($base);

        $variants[] = $singular;
        $variants[] = $plural;

        // Remove duplicate entries and empty strings
        return collect($variants)
            ->map(fn ($variant) => trim($variant))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function extractQuantity(string $message, string $variant): int
    {
        $patternBefore = '/(\d+)\s+' . preg_quote($variant, '/') . '/i';
        if (preg_match($patternBefore, $message, $matches)) {
            return (int) $matches[1];
        }

        $patternAfter = '/' . preg_quote($variant, '/') . '\s*(?:x|times)?\s*(\d+)/i';
        if (preg_match($patternAfter, $message, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    protected function extractMatchedText(string $message, string $variant): string
    {
        $pattern = '/(\d+\s+)?' . preg_quote($variant, '/') . '(\s*(?:x|times)?\s*\d+)?/i';
        if (preg_match($pattern, $message, $matches)) {
            return $matches[0];
        }

        return $variant;
    }
}
