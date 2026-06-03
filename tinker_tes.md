$order = \App\Models\Order::create([
    'order_number' => 'ORD-TEST-' . time(),
    'user_id' => 7, 
    'outlet_id' => 1, 
    'status' => 'paid',
    'subtotal' => 35000,
    'tax' => 3500,
    'delivery_fee' => 5000,
    'total_price' => 43500,
    'distance_real' => 2.0,
    'delivery_address' => 'Jl. Percobaan No. 88 (Simulasi)',
    'delivery_latitude' => -6.2, 
    'delivery_longitude' => 106.8,
    'created_at' => now(),
]);

\App\Models\OrderItem::create([
    'order_id' => $order->id,
    'product_id' => 1,
    'quantity' => 1,
    'product_name_snap' => 'Nasi Goreng Spesial',
    'product_price_snap' => 35000,
    'subtotal' => 35000,
]);