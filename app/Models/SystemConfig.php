<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    protected $fillable = [
        'delivery_base_price',
        'delivery_base_distance',
        'delivery_price_per_km',
        'platform_fee',
        'tax_percentage',
        
    ];

    protected $casts = [
        'delivery_base_price' => 'decimal:2',
        'delivery_base_distance' => 'decimal:2',
        'delivery_price_per_km' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
    ];

    /**
     * Get the latest system config
     */
    public static function getCurrent()
    {
        return self::first() ?? self::create([
            'delivery_base_price' => 5000,
            'delivery_base_distance' => 1,
            'delivery_price_per_km' => 2000,
            'platform_fee' => 0,
            'tax_percentage' => 10,
        ]);
    }
}
