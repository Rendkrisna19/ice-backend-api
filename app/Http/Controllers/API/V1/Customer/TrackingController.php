<?php

namespace App\Http\Controllers\API\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\DriverLocation;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    use ApiResponse;

    /**
     * Ambil posisi driver real-time + ETA + riwayat rute
     * GET /v1/customer/tracking/{order}
     */
    public function getTracking(Order $order)
    {
        $user = auth()->user();

        // Pastikan order milik customer yang login
        if ($order->user_id !== $user->id) {
            return $this->errorResponse('Anda tidak memiliki akses ke order ini', 403);
        }

        // Load relasi yang diperlukan
        $order->load(['driver', 'outlet']);

        // Jika order bukan on_delivery, kembalikan info dasar
        if ($order->status !== 'on_delivery') {
            return $this->successResponse([
                'status' => $order->status,
                'message' => 'Order belum dalam pengantaran',
                'driver' => null,
                'tracking' => null,
            ]);
        }

        $driver = $order->driver;

        // Ambil riwayat posisi driver untuk order ini (50 titik terakhir)
        $locationHistory = DriverLocation::where('order_id', $order->id)
            ->where('driver_id', $driver->id)
            ->orderBy('recorded_at', 'asc')
            ->limit(50)
            ->get(['latitude', 'longitude', 'recorded_at']);

        // Tentukan posisi driver: gunakan current_latitude jika ada, fallback ke outlet
        $driverLat = (float) ($driver->current_latitude ?? 0);
        $driverLng = (float) ($driver->current_longitude ?? 0);
        
        // Jika driver belum punya lokasi, gunakan posisi outlet sebagai fallback
        if ($driverLat == 0 || $driverLng == 0) {
            $driverLat = (float) ($order->outlet->latitude ?? 0);
            $driverLng = (float) ($order->outlet->longitude ?? 0);
        }

        $destLat = (float) ($order->delivery_latitude ?? 0);
        $destLng = (float) ($order->delivery_longitude ?? 0);

        // Hitung ETA + Rute menggunakan OSRM (real driving route)
        $routeData = null;
        $etaMinutes = 0;
        $distanceKm = 0;
        
        if ($driverLat != 0 && $driverLng != 0 && $destLat != 0 && $destLng != 0) {
            $routeData = $this->getOSRMRouteFull($driverLng, $driverLat, $destLng, $destLat);
            $etaMinutes = $routeData['duration_minutes'];
            $distanceKm = $routeData['distance_km'];
        }

        // Fallback ke Haversine jika OSRM gagal
        if ($etaMinutes == 0 && $driverLat != 0 && $destLat != 0) {
            $fallback = $this->calculateETA($driverLat, $driverLng, $destLat, $destLng);
            $etaMinutes = $fallback['minutes'];
            $distanceKm = $fallback['distance_km'];
        }

        return $this->successResponse([
            'status' => 'on_delivery',
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'photo' => $driver->profile_image ? url($driver->profile_image) : null,
                'vehicle_type' => $driver->vehicle_type ?? 'motor',
                'plate_number' => $driver->plate_number ?? '-',
                'current_latitude' => $driverLat,
                'current_longitude' => $driverLng,
                'location_updated_at' => $driver->location_updated_at,
            ],
            'outlet' => [
                'name' => $order->outlet->name ?? '-',
                'latitude' => (float) ($order->outlet->latitude ?? 0),
                'longitude' => (float) ($order->outlet->longitude ?? 0),
            ],
            'destination' => [
                'address' => $order->delivery_address,
                'latitude' => $destLat,
                'longitude' => $destLng,
            ],
            'eta_minutes' => $etaMinutes,
            'distance_remaining_km' => $distanceKm,
            'route_polyline' => $routeData ? json_encode($routeData['coordinates']) : null,
            'location_history' => $locationHistory->map(function ($loc) {
                return [
                    'latitude' => (float) $loc->latitude,
                    'longitude' => (float) $loc->longitude,
                    'recorded_at' => $loc->recorded_at,
                ];
            }),
        ]);
    }

    /**
     * Ambil rute navigasi driver (dari outlet ke customer)
     * GET /v1/driver/route/{order}
     */
    public function getRoute(Order $order)
    {
        $user = auth()->user();

        // Driver harus pemilik order ini
        if ($order->driver_id !== $user->id) {
            return $this->errorResponse('Order ini bukan tugas Anda', 403);
        }

        $order->load('outlet');

        $outletLat = (float) ($order->outlet->latitude ?? 0);
        $outletLng = (float) ($order->outlet->longitude ?? 0);
        $destLat = (float) ($order->delivery_latitude ?? 0);
        $destLng = (float) ($order->delivery_longitude ?? 0);

        if ($outletLat == 0 || $outletLng == 0 || $destLat == 0 || $destLng == 0) {
            return $this->errorResponse('Koordinat outlet atau tujuan tidak tersedia', 400);
        }

        // Ambil rute dari OSRM
        $routeData = $this->getOSRMRouteFull($outletLng, $outletLat, $destLng, $destLat);

        return $this->successResponse([
            'outlet' => [
                'name' => $order->outlet->name,
                'latitude' => $outletLat,
                'longitude' => $outletLng,
            ],
            'destination' => [
                'address' => $order->delivery_address,
                'latitude' => $destLat,
                'longitude' => $destLng,
            ],
            'route' => $routeData,
        ]);
    }

    /**
     * Hitung ETA dengan Haversine
     */
    private function calculateETA(float $driverLat, float $driverLng, float $destLat, float $destLng): array
    {
        if ($driverLat == 0 && $driverLng == 0) {
            return ['minutes' => 0, 'distance_km' => 0];
        }

        $earthRadius = 6371;
        $dLat = deg2rad($destLat - $driverLat);
        $dLng = deg2rad($destLng - $driverLng);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($driverLat)) * cos(deg2rad($destLat))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        // Default speed 25 km/jam
        $etaMinutes = ($distance / 25) * 60;

        return [
            'minutes' => max(1, (int) round($etaMinutes)),
            'distance_km' => round($distance, 2),
        ];
    }

    /**
     * Ambil polyline rute dari OSRM (gratis)
     * Returns: encoded polyline string atau null
     */
    private function getOSRMRoute(float $fromLng, float $fromLat, float $toLng, float $toLat): ?string
    {
        try {
            $url = "https://router.project-osrm.org/route/v1/driving/"
                . "{$fromLng},{$fromLat};{$toLng},{$toLat}"
                . "?overview=full&geometries=geojson";

            $response = @file_get_contents($url);
            if ($response === false) return null;

            $data = json_decode($response, true);
            if (!isset($data['routes'][0]['geometry']['coordinates'])) return null;

            // Konversi GeoJSON coordinates ke array [lat, lng]
            $coordinates = [];
            foreach ($data['routes'][0]['geometry']['coordinates'] as $coord) {
                $coordinates[] = [(float)$coord[1], (float)$coord[0]]; // [lat, lng]
            }

            return json_encode($coordinates);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Ambil data rute lengkap dari OSRM (jarak, durasi, geometri)
     */
    private function getOSRMRouteFull(float $fromLng, float $fromLat, float $toLng, float $toLat): array
    {
        try {
            $url = "https://router.project-osrm.org/route/v1/driving/"
                . "{$fromLng},{$fromLat};{$toLng},{$toLat}"
                . "?overview=full&geometries=geojson&steps=true";

            $response = @file_get_contents($url);
            if ($response === false) {
                return ['coordinates' => [], 'distance_km' => 0, 'duration_minutes' => 0];
            }

            $data = json_decode($response, true);
            if (!isset($data['routes'][0])) {
                return ['coordinates' => [], 'distance_km' => 0, 'duration_minutes' => 0];
            }

            $route = $data['routes'][0];

            // Konversi koordinat GeoJSON ke [lat, lng]
            $coordinates = [];
            foreach ($route['geometry']['coordinates'] as $coord) {
                $coordinates[] = [(float)$coord[1], (float)$coord[0]];
            }

            return [
                'coordinates' => $coordinates,
                'distance_km' => round($route['distance'] / 1000, 2),
                'duration_minutes' => (int) round($route['duration'] / 60),
            ];
        } catch (\Exception $e) {
            return ['coordinates' => [], 'distance_km' => 0, 'duration_minutes' => 0];
        }
    }
}
