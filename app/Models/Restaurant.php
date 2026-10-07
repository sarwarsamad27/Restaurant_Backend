<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\RestaurantRating;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'phone',
        'email',
        'address',
        'latitude',
        'longitude',
        'image',
        'cover_image',
        'opening_hours',
        'delivery_fee',
        'delivery_time',
        'minimum_order',
        'rating',
        'total_reviews',
        'status',
        'is_featured',
    ];

    protected $appends = [
        'image_url',
        'cover_image_url',
        'trust_score',
        'trust_badge',
        'ratings_breakdown',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'delivery_fee' => 'decimal:2',
        'minimum_order' => 'decimal:2',
        'rating' => 'decimal:2',
        'is_featured' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($restaurant) {
            if (empty($restaurant->slug)) {
                $restaurant->slug = Str::slug($restaurant->name);
            }
        });
    }

    // Relationships
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function communityRatings()
    {
        return $this->hasMany(RestaurantRating::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeNearby($query, $latitude, $longitude, $radius = 10)
    {
        // Simple distance calculation (for more accuracy, use Haversine formula)
        return $query->selectRaw("*, 
            (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
            cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
            sin(radians(latitude)))) AS distance", 
            [$latitude, $longitude, $latitude])
            ->having('distance', '<', $radius)
            ->orderBy('distance');
    }

    // Helper methods
    public function isOpen()
    {
        if ($this->status !== 'active') {
            return false;
        }

        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i');

        if (isset($this->opening_hours[$currentDay])) {
            $hours = $this->opening_hours[$currentDay];
            return $currentTime >= $hours['open'] && $currentTime <= $hours['close'];
        }

        return false;
    }

    public function updateRating()
    {
        $metrics = $this->calculateRatingMetrics();
        $this->rating = $metrics['trust_score'];
        $this->total_reviews = $metrics['total_ratings'];
        $this->save();
    }

    public function getTrustScoreAttribute(): float
    {
        return round((float) ($this->rating ?? 0), 2);
    }

    public function getTrustBadgeAttribute(): string
    {
        $score = $this->trust_score;

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

    public function getRatingsBreakdownAttribute(): array
    {
        $metrics = $this->calculateRatingMetrics();

        return [
            'taste' => $metrics['averages']['taste'],
            'quantity' => $metrics['averages']['quantity'],
            'hygiene' => $metrics['averages']['hygiene'],
            'value' => $metrics['averages']['value'],
            'total_ratings' => $metrics['total_ratings'],
        ];
    }

    protected function calculateRatingMetrics(): array
    {
        if (!Schema::hasTable('restaurant_ratings')) {
            return $this->legacyRatingMetrics();
        }

        $query = $this->communityRatings();

        if (!$query->exists()) {
            return $this->legacyRatingMetrics();
        }

        $aggregates = $query->selectRaw('
            AVG(average_score) as avg_score,
            AVG(rating_taste) as avg_taste,
            AVG(rating_quantity) as avg_quantity,
            AVG(rating_hygiene) as avg_hygiene,
            AVG(rating_value) as avg_value,
            COUNT(*) as total
        ')->first();

        return [
            'trust_score' => round((float) $aggregates->avg_score, 2),
            'total_ratings' => (int) $aggregates->total,
            'averages' => [
                'taste' => round((float) $aggregates->avg_taste, 2),
                'quantity' => round((float) $aggregates->avg_quantity, 2),
                'hygiene' => round((float) $aggregates->avg_hygiene, 2),
                'value' => round((float) $aggregates->avg_value, 2),
            ],
        ];
    }

    protected function legacyRatingMetrics(): array
    {
        $reviewAvg = (float) ($this->reviews()->avg('restaurant_rating') ?? 0);
        $reviewCount = $this->reviews()->count();

        return [
            'trust_score' => round($reviewAvg, 2),
            'total_ratings' => $reviewCount,
            'averages' => [
                'taste' => round($reviewAvg, 2),
                'quantity' => round($reviewAvg, 2),
                'hygiene' => round($reviewAvg, 2),
                'value' => round($reviewAvg, 2),
            ],
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }
}
