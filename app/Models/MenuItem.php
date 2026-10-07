<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuItem extends MongoModel
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'discount_price',
        'image',
        'images',
        'is_vegetarian',
        'is_vegan',
        'is_spicy',
        'is_available',
        'is_featured',
        'preparation_time',
        'ingredients',
        'allergens',
        'rating',
        'total_reviews',
        'sort_order',
        'calories',
        'protein',
        'carbs',
        'fats',
        'is_weight_loss',
        'is_weight_gain',
        'is_maintenance',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'images' => 'array',
        'is_vegetarian' => 'boolean',
        'is_vegan' => 'boolean',
        'is_spicy' => 'boolean',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'ingredients' => 'array',
        'allergens' => 'array',
        'rating' => 'decimal:2',
        'preparation_time' => 'integer',
        'total_reviews' => 'integer',
        'sort_order' => 'integer',
        'calories' => 'integer',
        'protein' => 'integer',
        'carbs' => 'integer',
        'fats' => 'integer',
        'is_weight_loss' => 'boolean',
        'is_weight_gain' => 'boolean',
        'is_maintenance' => 'boolean',
    ];

    protected $appends = ['image_url'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($menuItem) {
            if (empty($menuItem->slug)) {
                $menuItem->slug = Str::slug($menuItem->name);
            }
        });
    }

    // Relationships
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeVegetarian($query)
    {
        return $query->where('is_vegetarian', true);
    }

    // Helper methods
    public function getFinalPrice()
    {
        return $this->discount_price ?? $this->price;
    }

    public function hasDiscount()
    {
        return !is_null($this->discount_price) && $this->discount_price < $this->price;
    }

    public function getDiscountPercentage()
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        return round((($this->price - $this->discount_price) / $this->price) * 100);
    }
}
