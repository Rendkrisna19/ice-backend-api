<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create System Config
        SystemConfig::create([
            'delivery_base_price' => 5000,
            'delivery_base_distance' => 1,
            'delivery_price_per_km' => 2000,
            'tax_percentage' => 10,
        ]);

        // 2. Create Outlets
        $outlet1 = Outlet::create([
            'name' => 'Resto Nusantara',
            'slug' => 'resto-nusantara',
            'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
            'phone' => '021-1234567',
            'whatsapp_number' => '628123456789',
            'opening_hour' => '00:00:00',
            'closing_hour' => '23:59:00',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);

        $outlet2 = Outlet::create([
            'name' => 'Cafe Cozy',
            'slug' => 'cafe-cozy',
            'address' => 'Jl. Gatot Subroto No. 456, Jakarta Selatan',
            'phone' => '021-7654321',
            'whatsapp_number' => '628987654321',
            'opening_hour' => '00:00:00',
            'closing_hour' => '23:59:00',
            'latitude' => -6.2307,
            'longitude' => 106.7881,
        ]);

        // 3. Create Master Products
        $menuItems = [
            [
                'name' => 'Nasi Goreng Spesial',
                'description' => 'Nasi goreng dengan telur dan sayuran pilihan',
                'price' => 35000,
                'category' => 'Makanan',
                'image_url' => 'https://images.unsplash.com/photo-1603133872878-684f208fb74b?w=500&auto=format&fit=crop&q=60'
            ],
            [
                'name' => 'Mie Ayam Pangsit',
                'description' => 'Mie ayam dengan pangsit goreng',
                'price' => 28000,
                'category' => 'Makanan',
                'image_url' => 'https://images.unsplash.com/photo-1598514983318-2f64f8f4796c?w=500&auto=format&fit=crop&q=60'
            ],
            [
                'name' => 'Soto Ayam Tradisional',
                'description' => 'Soto ayam dengan resep tradisional',
                'price' => 25000,
                'category' => 'Makanan',
                'image_url' => 'https://images.unsplash.com/photo-1602370725287-3e1140027f60?w=500&auto=format&fit=crop&q=60'
            ],
            [
                'name' => 'Es Teh Manis',
                'description' => 'Minuman segar es teh manis',
                'price' => 8000,
                'category' => 'Minuman',
                'image_url' => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=500&auto=format&fit=crop&q=60'
            ],
            [
                'name' => 'Kopi Hitam',
                'description' => 'Kopi hitam panas',
                'price' => 12000,
                'category' => 'Minuman',
                'image_url' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=500&auto=format&fit=crop&q=60'
            ],
            [
                'name' => 'Cappuccino',
                'description' => 'Kopi dengan susu',
                'price' => 28000,
                'category' => 'Minuman',
                'image_url' => 'https://images.unsplash.com/photo-1572442388796-11668a67e53d?w=500&auto=format&fit=crop&q=60'
            ],
        ];

        foreach ($menuItems as $item) {
            $product = Product::create([
                'name' => $item['name'],
                'slug' => Str::slug($item['name']),
                'description' => $item['description'],
                'price' => $item['price'],
                'category' => $item['category'],
                'image_url' => $item['image_url'],
            ]);

            if (method_exists($product, 'outlets')) {
                $product->outlets()->attach($outlet1->id, [
                    'price' => $item['price'],
                    'is_available' => true
                ]);

                $product->outlets()->attach($outlet2->id, [
                    'price' => $item['price'] + 2000,
                    'is_available' => true
                ]);
            }
        }

        // 4. Create Users
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Kasir Nusantara',
            'email' => 'cashier1@example.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'outlet_id' => $outlet1->id,
        ]);

        User::create([
            'name' => 'Kasir Cozy',
            'email' => 'cashier2@example.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'outlet_id' => $outlet2->id,
        ]);

        $driver1 = User::create([
            'name' => 'Kurir Ahmad',
            'email' => 'driver1@example.com',
            'password' => Hash::make('password123'),
            'role' => 'driver',
            'outlet_id' => $outlet1->id,
            'is_online' => true, 
            'phone' => '08123456789',
            'plate_number' => 'B 1234 AB',
            'vehicle_type' => 'Motor',
            'wallet_balance' => 50000,
        ]);

        $driver2 = User::create([
            'name' => 'Kurir Budi',
            'email' => 'driver2@example.com',
            'password' => Hash::make('password123'),
            'role' => 'driver',
            'outlet_id' => $outlet1->id,
            'is_online' => true,
            'phone' => '08129876543',
            'plate_number' => 'B 5678 CD',
            'vehicle_type' => 'Motor',
        ]);

        User::create([
            'name' => 'Kurir Citra',
            'email' => 'driver3@example.com',
            'password' => Hash::make('password123'),
            'role' => 'driver',
            'outlet_id' => $outlet2->id,
            'is_online' => false,
        ]);

        $cust1 = User::create([
            'name' => 'John Doe',
            'email' => 'customer1@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'phone' => '081211112222'
        ]);

        $cust2 = User::create([
            'name' => 'Jane Smith',
            'email' => 'customer2@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'phone' => '081233334444'
        ]);

        // =========================================================
        // MEMBUAT 4 ORDERAN SEKALIGUS KE OUTLET 1 (RESTO NUSANTARA)
        // =========================================================

        $prodReferensi = Product::first(); 

        // ORDER 1
        $ordOdd = Order::create([
            'order_number' => 'ORD-ODD-' . Str::random(4),
            'user_id' => $cust1->id,
            'outlet_id' => $outlet1->id, 
            'status' => 'pending', 
            'subtotal' => 102411, 
            'tax' => 10241, 
            'delivery_fee' => 12000,
            'total_price' => 124652, 
            'delivery_address' => 'Jl. Uji Coba Pecahan No. 99',
            'created_at' => now(),
        ]);

        if ($prodReferensi) {
            OrderItem::create([
                'order_id' => $ordOdd->id,
                'product_id' => $prodReferensi->id,
                'quantity' => 1,
                'product_name_snap' => 'Paket Spesial Harga Pecahan',
                'product_price_snap' => 102411,
                'subtotal' => 102411,
            ]);
        }

        // ORDER 2
        $ordNew1 = Order::create([
            'order_number' => 'ORD-NEW-' . Str::random(4),
            'user_id' => $cust1->id,
            'outlet_id' => $outlet1->id, 
            'status' => 'pending', 
            'subtotal' => 70000, 
            'tax' => 7000, 
            'delivery_fee' => 15000,
            'total_price' => 92000, 
            'delivery_address' => 'Jl. Kebon Jeruk No. 10',
            'created_at' => now()->subMinutes(5), 
        ]);

        if ($prodReferensi) {
            OrderItem::create([
                'order_id' => $ordNew1->id,
                'product_id' => $prodReferensi->id,
                'quantity' => 2,
                'product_name_snap' => 'Nasi Goreng Spesial',
                'product_price_snap' => 35000,
                'subtotal' => 70000,
            ]);
        }

        // ORDER 3
        $ordNew2 = Order::create([
            'order_number' => 'ORD-NEW-' . Str::random(4),
            'user_id' => $cust2->id,
            'outlet_id' => $outlet1->id, 
            'status' => 'pending', 
            'subtotal' => 56000, 
            'tax' => 5600, 
            'delivery_fee' => 10000,
            'total_price' => 71600, 
            'delivery_address' => 'Apartemen Sudirman Park Tower A',
            'created_at' => now()->subMinutes(2), 
        ]);

        if ($prodReferensi) {
            OrderItem::create([
                'order_id' => $ordNew2->id,
                'product_id' => $prodReferensi->id,
                'quantity' => 2,
                'product_name_snap' => 'Mie Ayam Pangsit',
                'product_price_snap' => 28000,
                'subtotal' => 56000,
            ]);
        }
        
        // ORDER 4 
        $ordNew3 = Order::create([
            'order_number' => 'ORD-CZY-' . Str::random(4),
            'user_id' => $cust1->id,
            'outlet_id' => $outlet1->id, 
            'status' => 'pending', 
            'subtotal' => 56000, 
            'tax' => 5600, 
            'delivery_fee' => 12000,
            'total_price' => 73600, 
            'delivery_address' => 'Jl. Thamrin No. 8',
            'created_at' => now(),
        ]);

        if ($prodReferensi) {
            OrderItem::create([
                'order_id' => $ordNew3->id,
                'product_id' => $prodReferensi->id,
                'quantity' => 2,
                'product_name_snap' => 'Cappuccino',
                'product_price_snap' => 28000,
                'subtotal' => 56000,
            ]);
        }
    }
}