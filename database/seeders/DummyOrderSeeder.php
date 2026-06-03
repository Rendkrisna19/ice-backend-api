<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DummyOrderSeeder extends Seeder
{
    public function run()
    {
        // 1. SETUP MERCHANT & DRIVER
        $cashier = User::where('email', 'cashier1@example.com')->first();
        if (!$cashier) return;
        $outletId = $cashier->outlet_id;

        $driver = User::where('email', 'driver2@example.com')->first();
        if ($driver) {
            $driver->update(['is_online' => true, 'is_busy' => false]);
        }

        // Ambil Produk
        $nasiGoreng = Product::where('slug', 'nasi-goreng-spesial')->first();
        $esTeh = Product::where('slug', 'es-teh-manis')->first();

        // 2. SKENARIO PESANAN DENGAN USER BERBEDA
        $scenarios = [
            [
                'status' => 'paid',
                'name' => 'Budi Santoso', // Nama Customer
                'email' => 'budi@test.com',
                'items' => [['p' => $nasiGoreng, 'qty' => 2], ['p' => $esTeh, 'qty' => 2]]
            ],
            [
                'status' => 'preparing',
                'name' => 'Siti Aminah',
                'email' => 'siti@test.com',
                'items' => [['p' => $nasiGoreng, 'qty' => 1]]
            ],
            [
                'status' => 'ready',
                'name' => 'Joko Anwar',
                'email' => 'joko@test.com',
                'items' => [['p' => $esTeh, 'qty' => 5]]
            ],
            [
                'status' => 'on_delivery',
                'name' => 'Rina Nose',
                'email' => 'rina@test.com',
                'driver_id' => $driver->id,
                'items' => [['p' => $nasiGoreng, 'qty' => 1]]
            ],
        ];

        foreach ($scenarios as $idx => $scene) {
            
            // --- FIX: BUAT USER BARU UNTUK SETIAP PESANAN ---
            // Cek dulu kalo user udah ada biar gak error duplicate entry
            $customer = User::firstOrCreate(
                ['email' => $scene['email']], 
                [
                    'name' => $scene['name'],
                    'password' => Hash::make('password'),
                    'role' => 'customer'
                ]
            );

            // Hitung Harga
            $subtotal = 0;
            foreach ($scene['items'] as $item) {
                $subtotal += $item['p']->price * $item['qty'];
            }
            $tax = $subtotal * 0.1;
            $total = $subtotal + $tax + 10000;

            // Buat Order
            $order = Order::create([
                'order_number' => 'ORD-' . date('dHis') . '-' . $idx,
                'user_id' => $customer->id, // Link ke user Budi/Siti/Joko
                'outlet_id' => $outletId,
                'driver_id' => $scene['driver_id'] ?? null,
                'status' => $scene['status'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'delivery_fee' => 10000,
                'total_price' => $total,
                'distance_real' => 2.5,
                'delivery_address' => 'Jl. Testing No. ' . ($idx + 1),
                'delivery_latitude' => -6.2,
                'delivery_longitude' => 106.8,
                'created_at' => now()->subMinutes((4 - $idx) * 10),
            ]);

            // Buat Item
            foreach ($scene['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['p']->id,
                    'quantity' => $item['qty'],
                    'product_name_snap' => $item['p']->name,
                    'product_price_snap' => $item['p']->price,
                    'subtotal' => $item['p']->price * $item['qty'],
                ]);
            }
        }
    }
}