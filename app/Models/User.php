<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
    'name',
    'email',
    'email_verified_at',
    'password',
    'role',
    'outlet_id',
    'is_online',
    'is_busy',
    'phone',
    'profile_photo_path',
    'plate_number',
    'vehicle_type',
    'wallet_balance',
    'profile_image',
    'status',
    'current_latitude',
    'current_longitude',
    'location_updated_at',
];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_online' => 'boolean',
            'is_busy' => 'boolean',
        ];
    }

    /**
     * Get the outlet this user belongs to
     */
    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Get orders created by this user (customer)
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    /**
     * Get deliveries assigned to this driver
     */
    public function deliveries()
    {
        return $this->hasMany(Order::class, 'driver_id');
    }

    /**
     * Get driver location history
     */
    public function locations()
    {
        return $this->hasMany(DriverLocation::class, 'driver_id');
    }
}
