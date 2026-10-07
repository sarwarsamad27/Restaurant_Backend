<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'restaurant_id',
        'driver_id',
        'order_type',
        'status',
        'payment_status',
        'payment_method',
        'payment_transaction_id',
        'subtotal',
        'tax',
        'delivery_fee',
        'discount',
        'total',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'customer_phone',
        'special_instructions',
        'table_number',
        'confirmed_at',
        'preparing_at',
        'ready_at',
        'out_for_delivery_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'confirmed_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-' . strtoupper(uniqid());
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    // Helper methods
    public function updateStatus($status)
    {
        $this->status = $status;
        
        $timestampMap = [
            'confirmed' => 'confirmed_at',
            'preparing' => 'preparing_at',
            'ready' => 'ready_at',
            'out_for_delivery' => 'out_for_delivery_at',
            'delivered' => 'delivered_at',
            'cancelled' => 'cancelled_at',
        ];

        if (isset($timestampMap[$status])) {
            $this->{$timestampMap[$status]} = now();
        }

        // Cash is collected on delivery
        if ($status === 'delivered' && $this->payment_method === 'cash') {
            $this->payment_status = 'paid';
        }

        $this->save();
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function isDelivered()
    {
        return $this->status === 'delivered';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }
}
