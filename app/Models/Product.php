<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'outlet_id',
        'name',
        'slug',
        'description',
        'price',
        'cost_price',
        'image_url',
        'category',
        'is_available',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    public function outlets()
    {
        return $this->belongsToMany(Outlet::class, 'outlet_product')
            ->withPivot(['price', 'is_available']);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Otomatis memformat image_url agar selalu menggunakan URL production
     * dan mengubah localhost menjadi URL yang benar saat diakses.
     */
    public function getImageUrlAttribute($value)
    {
        if (empty($value)) return $value;

        // Jika value adalah relative path (contoh: storage/...), ubah menjadi full URL berdasarkan APP_URL
        if (str_starts_with($value, 'storage/')) {
            return url($value);
        }

        return $value;
    }
}