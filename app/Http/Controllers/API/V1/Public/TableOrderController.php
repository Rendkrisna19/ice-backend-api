<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SystemConfig;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableOrderController extends Controller
{
    use ApiResponse;

    /**
     * Resolve a table by its QR token and return outlet + menu info
     */
    public function show(string $token)
    {
        $table = DiningTable::with('outlet')->where('qr_token', $token)->first();

        if (!$table) {
            return $this->errorResponse('Meja tidak ditemukan. Coba scan ulang QR code.', 404);
        }

        if (!$table->outlet->isOpenNow()) {
            return $this->errorResponse('Maaf, Toko Sedang Tutup.', 403);
        }

        $products = Product::where('outlet_id', $table->outlet_id)
            ->where('is_available', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return $this->successResponse([
            'table' => [
                'id' => $table->id,
                'name' => $table->name,
            ],
            'outlet' => [
                'id' => $table->outlet->id,
                'name' => $table->outlet->name,
            ],
            'products' => $products,
        ], 'Menu retrieved successfully');
    }

    /**
     * Place a new dine-in order from a table (guest, no auth)
     */
    public function store(Request $request, string $token)
    {
        $table = DiningTable::with('outlet')->where('qr_token', $token)->first();

        if (!$table) {
            return $this->errorResponse('Meja tidak ditemukan. Coba scan ulang QR code.', 404);
        }

        if (!$table->outlet->isOpenNow()) {
            return $this->errorResponse('Maaf, Toko Sedang Tutup.', 403);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        // Re-fetch products from DB so price/availability can never be spoofed by the client
        $productIds = collect($validated['items'])->pluck('product_id')->unique();
        $products = Product::where('outlet_id', $table->outlet_id)
            ->where('is_available', true)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($productIds as $productId) {
            if (!$products->has($productId)) {
                return $this->errorResponse("Produk tidak tersedia di outlet ini.", 422);
            }
        }

        return DB::transaction(function () use ($validated, $table, $products) {
            // Sapu bersih pesanan dibatalkan dari sesi sebelumnya yang masih "menggantung"
            // (belum ke-paid_at karena tidak ada yang dibayar) supaya tidak ikut nyangkut
            // ke sesi baru begitu meja ini dipakai pelanggan lain.
            Order::where('table_id', $table->id)
                ->where('order_type', 'dine_in')
                ->where('status', 'cancelled')
                ->whereNull('paid_at')
                ->update(['paid_at' => now()]);

            $taxPercentage = SystemConfig::getCurrent()->tax_percentage ?? 10;

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $products[$item['product_id']]->price * $item['quantity'];
            }
            $tax = ($subtotal * $taxPercentage) / 100;

            $orderNumber = 'DINEIN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => null,
                'outlet_id' => $table->outlet_id,
                'order_type' => 'dine_in',
                'table_id' => $table->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'tax' => $tax,
                'delivery_fee' => 0,
                'total_price' => $subtotal + $tax,
            ]);

            foreach ($validated['items'] as $item) {
                $product = $products[$item['product_id']];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'product_name_snap' => $product->name,
                    'product_price_snap' => $product->price,
                    'variant_snap' => isset($item['notes']) ? ['notes' => $item['notes']] : null,
                    'subtotal' => $product->price * $item['quantity'],
                ]);
            }

            return $this->successResponse(
                $order->load('items', 'table'),
                'Pesanan berhasil dikirim ke dapur'
            );
        });
    }

    /**
     * List all current open-session orders for a table (shared across every device
     * that scans the same QR code, not just the device that placed the order).
     */
    public function orders(string $token)
    {
        $table = DiningTable::where('qr_token', $token)->first();

        if (!$table) {
            return $this->errorResponse('Meja tidak ditemukan. Coba scan ulang QR code.', 404);
        }

        $orders = Order::where('table_id', $table->id)
            ->where('order_type', 'dine_in')
            ->whereNull('paid_at')
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($orders, 'Orders retrieved successfully');
    }
}
