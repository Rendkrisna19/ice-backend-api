<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Outlet;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Support\Str;

class OrderService
{
    protected DeliveryService $deliveryService;

    public function __construct()
    {
        $this->deliveryService = new DeliveryService();
    }

    /**
     * Create a new order with items
     */
    public function createOrder(array $data, array $items): Order
    {
        $orderNumber = 'ORD-' . strtoupper(Str::random(8)) . '-' . time();

        // [FIX] Subtotal dihitung sebagai int dari awal — tidak ada float
        $subtotal = 0;
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            // [FIX] cast price ke int agar tidak ada desimal dari DB
            $itemSubtotal = $item['quantity'] * (int) $product->price;
            $subtotal += $itemSubtotal;
        }

        // Ambil jarak asli dari Flutter
        $distance = (float) ($data['distance_real'] ?? 1);
        
        // [FIX] Hitung ongkir — DeliveryService sudah return int via ceil()
        $deliveryCalc = $this->deliveryService->calculateDeliveryFee($distance);
        $deliveryFee  = (int) (is_array($deliveryCalc) ? $deliveryCalc['delivery_fee'] : $deliveryCalc);

        // [FIX] Hitung total dan pajak — DeliveryService sudah return int via ceil()
        $totalCalc  = $this->deliveryService->calculateTotal((float) $subtotal, (float) $deliveryFee);
        $tax        = (int) (is_array($totalCalc) ? $totalCalc['tax']        : ceil(($subtotal * 10) / 100));
        $totalPrice = (int) (is_array($totalCalc) ? $totalCalc['total_price'] : ($subtotal + $tax + $deliveryFee));

        // [FIX] Semua nilai yang disimpan ke DB sudah int — tidak ada koma di belakang
        $order = Order::create([
            'order_number'       => $orderNumber,
            'user_id'            => $data['user_id'],
            'outlet_id'          => $data['outlet_id'],
            'status'             => 'pending',
            'subtotal'           => $subtotal,    // int
            'tax'                => $tax,         // int
            'delivery_fee'       => $deliveryFee, // int
            'total_price'        => $totalPrice,  // int
            'distance_real'      => $distance,
            'delivery_address'   => $data['delivery_address'],
            'delivery_latitude'  => $data['delivery_latitude']  ?? null,
            'delivery_longitude' => $data['delivery_longitude'] ?? null,
        ]);

        // Create order items dengan snapshot harga
        foreach ($items as $item) {
            $product      = Product::find($item['product_id']);
            $priceSnap    = (int) $product->price; // [FIX] cast ke int
            $itemSubtotal = $item['quantity'] * $priceSnap;

            OrderItem::create([
                'order_id'           => $order->id,
                'product_id'         => $product->id,
                'quantity'           => $item['quantity'],
                'product_name_snap'  => $product->name,
                'product_price_snap' => $priceSnap,    // int
                'variant_snap'       => $item['variant_snap'] ?? null,
                'subtotal'           => $itemSubtotal, // int
            ]);
        }

        return $order->load('items');
    }

    /**
     * Validate order before checkout
     */
    public function validateCheckout(int $outletId, array $items): array
    {
        $outlet = Outlet::find($outletId);

        if (!$outlet) {
            return [
                'valid'   => false,
                'message' => 'Outlet tidak ditemukan',
            ];
        }

        $now = now();
        if (
            $now->format('H:i:s') < $outlet->opening_hour->format('H:i:s') ||
            $now->format('H:i:s') > $outlet->closing_hour->format('H:i:s') ||
            $outlet->is_force_closed
        ) {
            return [
                'valid'   => false,
                'message' => 'Toko sedang tutup',
            ];
        }

        $invalidItems = [];
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            if (!$product) {
                $invalidItems[] = "Produk {$item['product_id']} tidak ditemukan";
                continue;
            }

            if ($product->outlet_id != $outletId || !$product->is_available) {
                $invalidItems[] = "Produk {$product->name} tidak tersedia di toko ini";
            }
        }

        if (!empty($invalidItems)) {
            return [
                'valid'   => false,
                'message' => 'Validasi gagal: ' . implode(', ', $invalidItems),
            ];
        }

        return [
            'valid'   => true,
            'message' => 'Validasi berhasil',
        ];
    }

    /**
     * Cancel order
     */
    public function cancelOrder(Order $order): bool
    {
        if (!$order->canBeCancelled()) {
            return false;
        }

        $order->update(['status' => 'cancelled']);
        return true;
    }

    /**
     * Reassign driver
     */
    public function reassignDriver(Order $order, int $driverId): bool
    {
        if (!$order->canReassignDriver()) {
            return false;
        }

        $order->update(['driver_id' => $driverId]);
        return true;
    }

    /**
     * Mark order as paid
     */
    public function markAsPaid(Order $order): Order
    {
        $order->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return $order;
    }

    /**
     * Update order status
     */
    public function updateStatus(Order $order, string $status): Order
    {
        if ($order->status === 'cancelled') {
            throw new \Exception('Cannot update status of a cancelled order');
        }

        $order->update(['status' => $status]);

        if ($status === 'completed') {
            $order->update(['completed_at' => now()]);
        }

        return $order;
    }
}
