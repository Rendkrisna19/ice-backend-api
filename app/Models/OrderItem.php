<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'product_name_snap',  // Nama produk saat dibeli
        'product_price_snap', // Harga produk saat dibeli
        'variant_snap',       // JSON varian (optional)
        'subtotal',           // (price * qty)
    ];

    protected $casts = [
        'quantity' => 'integer',
        'product_price_snap' => 'decimal:2',
        'variant_snap' => 'array', // Gunakan 'array' agar otomatis jadi JSON di DB
        'subtotal' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}