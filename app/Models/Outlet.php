<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'address',
        'latitude',
        'longitude',
        'phone',
        'whatsapp_number',
        'opening_hour',
        'closing_hour',
        'is_force_closed',
        'logo',
        'banner',
    ];

    protected $casts = [
        'opening_hour' => 'datetime:H:i:s',
        'closing_hour' => 'datetime:H:i:s',
        'is_force_closed' => 'boolean',
    ];

    /**
     * Get all users (cashiers/drivers) for this outlet
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all orders for this outlet
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get all products available in this outlet (Many-to-Many)
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'outlet_product')
            ->withPivot(['price', 'is_available']);
    }
}
