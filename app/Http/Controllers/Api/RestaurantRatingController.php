<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantRating;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class RestaurantRatingController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required to submit a rating.',
            ], 401);
        }

        $data = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'order_id' => 'nullable|exists:orders,id',
            'rating_taste' => 'required|integer|min:1|max:5',
            'rating_quantity' => 'required|integer|min:1|max:5',
            'rating_hygiene' => 'required|integer|min:1|max:5',
            'rating_value' => 'required|integer|min:1|max:5',
        ]);

        $rating = RestaurantRating::updateOrCreate(
            [
                'restaurant_id' => $data['restaurant_id'],
                'user_id' => $user->id,
            ],
            [
                'order_id' => $data['order_id'] ?? null,
                'rating_taste' => $data['rating_taste'],
                'rating_quantity' => $data['rating_quantity'],
                'rating_hygiene' => $data['rating_hygiene'],
                'rating_value' => $data['rating_value'],
            ]
        );

        $restaurant = $rating->restaurant; // Relation ensures metrics are fresh
        $restaurant->updateRating();

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted successfully.',
            'data' => $this->buildSummary($restaurant),
        ], 201);
    }

    public function show(Restaurant $restaurant)
    {
        return response()->json([
            'success' => true,
            'data' => $this->buildSummary($restaurant),
        ]);
    }

    public function publicReviews(Request $request, Restaurant $restaurant)
    {
        return $this->paginateRatings($request, $restaurant, includeEmail: false);
    }

    public function adminIndex(Request $request, Restaurant $restaurant)
    {
        return $this->paginateRatings($request, $restaurant, includeEmail: true);
    }

    protected function buildSummary(Restaurant $restaurant): array
    {
        $breakdown = $restaurant->ratings_breakdown;

        return [
            'restaurant_id' => $restaurant->id,
            'trust_score' => $restaurant->trust_score,
            'trust_badge' => $restaurant->trust_badge,
            'averages' => [
                'taste' => $breakdown['taste'],
                'quantity' => $breakdown['quantity'],
                'hygiene' => $breakdown['hygiene'],
                'value' => $breakdown['value'],
            ],
            'total_ratings' => $breakdown['total_ratings'],
        ];
    }

    protected function paginateRatings(Request $request, Restaurant $restaurant, bool $includeEmail = false)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 50));

        /** @var LengthAwarePaginator $ratings */
        $ratings = $restaurant->communityRatings()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $ratings->getCollection()->transform(function (RestaurantRating $rating) use ($includeEmail) {
            return $this->transformRating($rating, $includeEmail);
        });

        return response()->json([
            'success' => true,
            'data' => $ratings->items(),
            'meta' => [
                'current_page' => $ratings->currentPage(),
                'last_page' => $ratings->lastPage(),
                'per_page' => $ratings->perPage(),
                'total' => $ratings->total(),
            ],
        ]);
    }

    protected function transformRating(RestaurantRating $rating, bool $includeEmail = false): array
    {
        $user = $rating->user;

        return [
            'id' => $rating->id,
            'user' => array_filter([
                'id' => $user?->id,
                'name' => $user?->name ?? 'Anonymous',
                'email' => $includeEmail ? ($user?->email ?? null) : null,
            ], fn ($value) => $value !== null),
            'ratings' => [
                'taste' => $rating->rating_taste,
                'quantity' => $rating->rating_quantity,
                'hygiene' => $rating->rating_hygiene,
                'value' => $rating->rating_value,
            ],
            'average_score' => $rating->average_score,
            'trust_badge' => $rating->trust_badge,
            'submitted_at' => $rating->created_at?->toDateTimeString(),
        ];
    }
}
