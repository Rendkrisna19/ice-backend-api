<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $driver_id;
    public int $order_id;
    public float $latitude;
    public float $longitude;
    public ?float $speed;
    public ?float $heading;
    public int $eta_minutes;
    public float $distance_remaining;
    public string $recorded_at;

    public function __construct(array $data)
    {
        $this->driver_id = $data['driver_id'];
        $this->order_id = $data['order_id'];
        $this->latitude = $data['latitude'];
        $this->longitude = $data['longitude'];
        $this->speed = $data['speed'] ?? null;
        $this->heading = $data['heading'] ?? null;
        $this->eta_minutes = $data['eta_minutes'] ?? 0;
        $this->distance_remaining = $data['distance_remaining'] ?? 0;
        $this->recorded_at = $data['recorded_at'] ?? now()->toISOString();
    }

    public function broadcastOn()
    {
        return new PrivateChannel('order.' . $this->order_id . '.tracking');
    }

    public function broadcastWith()
    {
        return [
            'driver_id' => $this->driver_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'speed' => $this->speed,
            'heading' => $this->heading,
            'eta_minutes' => $this->eta_minutes,
            'distance_remaining' => $this->distance_remaining,
            'recorded_at' => $this->recorded_at,
        ];
    }
}
