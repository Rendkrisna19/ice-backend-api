<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'outlet_id',
        'driver_id',
        'status',
        'subtotal',
        'tax',
        'delivery_fee',
        'total_price',
        'distance_real',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'paid_at',
        'completed_at',
        // --- TAMBAHAN WAJIB UTK DRIVER APP ---
        'picked_up_at',      // Waktu driver ambil barang
        'delivered_at',      // Waktu driver sampai
        'proof_of_delivery', // URL Foto Bukti
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_price' => 'decimal:2',
        'distance_real' => 'decimal:2',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'picked_up_at' => 'datetime', // Cast juga ini
        'delivered_at' => 'datetime', // Cast juga ini
    ];

    /**
     * Get the customer who made this order
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the outlet for this order
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user() { return $this->belongsTo(User::class); }


    /**
     * Get the driver assigned to this order
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get all items in this order
     */
   public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'paid']);
    }

    /**
     * Check if driver can be reassigned
     */
    public function canReassignDriver(): bool
    {
        return $this->status !== 'on_delivery' && $this->status !== 'completed';
    }
}
