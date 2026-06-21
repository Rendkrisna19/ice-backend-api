<?php

namespace App\Http\Controllers\API\V1\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
use App\Models\Order;
use App\Models\User;
use App\Events\DriverLocationUpdated;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    use ApiResponse;

    /**
     * Terima koordinat GPS dari driver (dipanggil setiap 5 detik)
     * POST /v1/driver/location
     */
    public function updateLocation(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'driver') {
            return $this->errorResponse('Hanya driver yang dapat mengirim lokasi', 403);
        }

        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
        ]);

        // Pastikan order ini milik driver yang login & sedang on_delivery
        $order = Order::where('id', $validated['order_id'])
            ->where('driver_id', $user->id)
            ->where('status', 'on_delivery')
            ->first();

        if (!$order) {
            return $this->errorResponse('Order tidak ditemukan atau bukan dalam pengantaran Anda', 404);
        }

        // 1. Update posisi terakhir di tabel users (untuk query cepat)
        $user->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
            'location_updated_at' => now(),
        ]);

        // 2. Simpan riwayat ke driver_locations
        $location = DriverLocation::create([
            'driver_id' => $user->id,
            'order_id' => $validated['order_id'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'speed' => $validated['speed'] ?? null,
            'heading' => $validated['heading'] ?? null,
            'recorded_at' => now(),
        ]);

        // 3. Hitung ETA & jarak sisa menggunakan OSRM (real driving route)
        $eta = $this->calculateETAFromOSRM(
            $validated['latitude'],
            $validated['longitude'],
            $order->delivery_latitude,
            $order->delivery_longitude
        );

        // Fallback ke Haversine jika OSRM gagal
        if ($eta === null) {
            $eta = $this->calculateETA(
                $validated['latitude'],
                $validated['longitude'],
                $order->delivery_latitude,
                $order->delivery_longitude,
                $validated['speed'] ?? 25
            );
        }

        // 4. Broadcast ke customer (optional, skip jika tidak dikonfigurasi)
        try {
            if (class_exists(\App\Events\DriverLocationUpdated::class)) {
                broadcast(new \App\Events\DriverLocationUpdated([
                    'driver_id' => $user->id,
                    'order_id' => $validated['order_id'],
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'eta_minutes' => $eta['minutes'],
                    'distance_remaining' => $eta['distance_km'],
                ]));
            }
        } catch (\Exception $e) {
            // Skip broadcast jika belum dikonfigurasi (Hostinger shared hosting)
        }

        return $this->successResponse([
            'eta_minutes' => $eta['minutes'],
            'distance_remaining' => $eta['distance_km'],
        ], 'Lokasi berhasil diperbarui');
    }

    /**
     * Hitung ETA menggunakan rumus Haversine
     */
    private function calculateETA(float $driverLat, float $driverLng, float $destLat, float $destLng, float $speedKmh): array
    {
        // Haversine formula untuk jarak (km)
        $earthRadius = 6371;
        $dLat = deg2rad($destLat - $driverLat);
        $dLng = deg2rad($destLng - $driverLng);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($driverLat)) * cos(deg2rad($destLat))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        // Kecepatan default 25 km/jam jika speed = 0 atau null
        $effectiveSpeed = ($speedKmh > 0) ? $speedKmh : 25;

        // ETA dalam menit
        $etaMinutes = ($distance / $effectiveSpeed) * 60;

        return [
            'minutes' => max(1, (int) round($etaMinutes)),
            'distance_km' => round($distance, 2),
        ];
    }

    /**
     * Hitung ETA menggunakan OSRM (real driving route)
     * Lebih akurat dari Haversine karena mengikuti jalan raya
     */
    private function calculateETAFromOSRM(float $driverLat, float $driverLng, float $destLat, float $destLng): ?array
    {
        try {
            $url = "https://router.project-osrm.org/route/v1/driving/"
                . "{$driverLng},{$driverLat};{$destLng},{$destLat}"
                . "?overview=false";

            $response = @file_get_contents($url);
            if ($response === false) return null;

            $data = json_decode($response, true);
            if (!isset($data['routes'][0])) return null;

            $route = $data['routes'][0];

            return [
                'minutes' => max(1, (int) round($route['duration'] / 60)),
                'distance_km' => round($route['distance'] / 1000, 2),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
