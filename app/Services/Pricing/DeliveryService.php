<?php

namespace App\Services\Order;

use App\Models\SystemConfig;

class DeliveryService
{
    /**
     * Hitung ongkos kirim berdasarkan jarak (km)
     * Formula: BasePrice + Max(0, (Distance - BaseDistance) * PricePerKM)
     * Semua nilai dibulatkan dengan ceil() agar tidak ada koma (,82)
     */
    public function calculateDeliveryFee(float $distance): array
    {
        $config = SystemConfig::getCurrent();

        $basePrice    = (float) ($config->delivery_base_price    ?? $config->base_price    ?? 5000);
        $baseDistance = (float) ($config->delivery_base_distance ?? $config->base_distance ?? 1);
        $pricePerKm   = (float) ($config->delivery_price_per_km  ?? $config->extra_price_per_km ?? 2000);

        $extraDistance = max(0, $distance - $baseDistance);

        // [FIX] ceil() wajib — hilangkan koma di belakang
        $deliveryFee = (int) ceil($basePrice + ($extraDistance * $pricePerKm));

        return [
            'base_price'     => (int) $basePrice,
            'extra_distance' => round($extraDistance, 2),
            'price_per_km'   => (int) $pricePerKm,
            'delivery_fee'   => $deliveryFee, // sudah int, tidak ada koma
        ];
    }

    /**
     * Hitung total pembayaran (subtotal + pajak + ongkir)
     * Semua nilai dibulatkan dengan ceil()
     */
    public function calculateTotal(float $subtotal, float $deliveryFee): array
    {
        $config = SystemConfig::getCurrent();

        $taxPercentage = (float) ($config->tax_percentage ?? $config->tax ?? 10);
        $platformFee   = (int) ($config->platform_fee ?? 0);

        // [FIX] ceil() pada pajak — hindari floating point error
        $tax        = (int) ceil(($subtotal * $taxPercentage) / 100);
        $totalPrice = (int) ceil($subtotal + $tax + $deliveryFee + $platformFee);

        return [
            'subtotal'     => (int) $subtotal,
            'tax'          => $tax,        // sudah int
            'delivery_fee' => (int) $deliveryFee, // sudah int
            'platform_fee' => $platformFee,
            'total_price'  => $totalPrice, // sudah int
        ];
    }
}
