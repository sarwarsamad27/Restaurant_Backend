<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class RestaurantRating extends MongoModel
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'order_id',
        'rating_taste',
        'rating_quantity',
        'rating_hygiene',
        'rating_value',
        'average_score',
    ];

    protected $casts = [
        'rating_taste' => 'integer',
        'rating_quantity' => 'integer',
        'rating_hygiene' => 'integer',
        'rating_value' => 'integer',
        'average_score' => 'float',
    ];

    protected $appends = [
        'trust_badge',
    ];

    protected static function booted(): void
    {
        static::saving(function (RestaurantRating $rating) {
            $rating->average_score = round((
                ($rating->rating_taste ?? 0) +
                ($rating->rating_quantity ?? 0) +
                ($rating->rating_hygiene ?? 0) +
                ($rating->rating_value ?? 0)
            ) / 4, 2);
        });
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getTrustBadgeAttribute(): string
    {
        $score = $this->average_score;

        if ($score >= 4.5) {
            return 'Diamond';
        }
        if ($score >= 3.5) {
            return 'Gold';
        }
        if ($score >= 2.0) {
            return 'Silver';
        }

        return 'Bronze';
    }
}
