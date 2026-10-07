<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Driver extends MongoModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_number',
        'license_number',
        'current_latitude',
        'current_longitude',
        'status',
        'is_verified',
        'rating',
        'total_deliveries',
        'total_earnings',
    ];

    protected $casts = [
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'is_verified' => 'boolean',
        'rating' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'total_deliveries' => 'integer',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Order::class, 'driver_id', 'user_id');
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_verified', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    // Helper methods
    public function updateLocation($latitude, $longitude)
    {
        $this->current_latitude = $latitude;
        $this->current_longitude = $longitude;
        $this->save();
    }

    public function markAsAvailable()
    {
        $this->status = 'available';
        $this->save();
    }

    public function markAsBusy()
    {
        $this->status = 'busy';
        $this->save();
    }

    public function markAsOffline()
    {
        $this->status = 'offline';
        $this->save();
    }
}
