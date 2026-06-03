<?php

namespace App\Services\Connectivity;

class DeliveryService
{
    // Base delivery fee
    private const BASE_DELIVERY_FEE = 8000; // Rp 8,000
    private const BASE_DISTANCE = 2; // km
    private const PRICE_PER_KM = 2000; // Rp 2,000 per km

    /**
     * Calculate delivery fee based on distance
     */
    public function calculateDeliveryFee($distance)
    {
        $distance = (float) $distance;
        
        if ($distance <= self::BASE_DISTANCE) {
            return self::BASE_DELIVERY_FEE;
        }

        $additionalDistance = $distance - self::BASE_DISTANCE;
        $additionalFee = $additionalDistance * self::PRICE_PER_KM;
        
        return self::BASE_DELIVERY_FEE + $additionalFee;
    }

    /**
     * Calculate total price (subtotal + tax + delivery fee)
     */
    public function calculateTotal($subtotal, $deliveryFee, $taxPercentage = 10)
    {
        $tax = ($subtotal * $taxPercentage) / 100;
        return $subtotal + $tax + $deliveryFee;
    }
}
