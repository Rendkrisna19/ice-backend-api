<?php

use Illuminate\Support\Facades\Broadcast;

// Override the auto-registered broadcast route to use Sanctum token auth
// (the default registration from bootstrap/app.php uses 'web' middleware)
Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel private untuk chat transaksi
Broadcast::channel('chat.transaction.{transactionId}', function ($user, $transactionId) {
    // Hanya customer/driver yang terlibat transaksi boleh join
    $order = \App\Models\Order::find($transactionId);
    if (!$order) return false;
    // Order uses user_id for the customer (not customer_id)
    return $user->id === $order->user_id || $user->id === $order->driver_id;
});

// Channel private untuk real-time tracking driver
Broadcast::channel('order.{orderId}.tracking', function ($user, $orderId) {
    $order = \App\Models\Order::find($orderId);
    if (!$order) return false;
    // Hanya customer yang pesan & driver yang mengantar
    return $user->id === $order->user_id || $user->id === $order->driver_id;
});
