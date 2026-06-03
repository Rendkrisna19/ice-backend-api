<?php

namespace App\Http\Controllers\API\V1\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Exception;

class PosController extends Controller
{
    /**
     * Mengambil produk aktif berdasarkan outlet kasir yang login
     */
    public function getProducts(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->outlet_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak terikat dengan outlet manapun.'
                ], 403);
            }

            // Mengambil produk yang tersedia di outlet tersebut
            $products = Product::where('outlet_id', $user->outlet_id)
                ->where('is_available', 1)
                ->orderBy('name', 'asc')
                ->get()
                ->map(function ($product) {
                    // LOGIC GAMBAR: Kembalikan path relatif saja
                    if ($product->image_url && !str_starts_with($product->image_url, 'http')) {
                        $product->image_url = $product->image_url;
                    }
                    return $product;
                });

            return response()->json([
                'status' => 'success',
                'data' => $products
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil produk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengambil daftar pesanan yang masih 'pending' (Antrean Kasir)
     */
    public function getActiveOrders(Request $request)
    {
        try {
            $user = $request->user();
            
            $orders = Order::where('outlet_id', $user->outlet_id)
                ->where('status', 'pending')
                ->with('items') // Memuat item untuk keperluan cetak struk/detail
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $orders
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil antrean: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengambil konfigurasi PPN terbaru dari system_configs
     */
    public function getConfigs()
    {
        try {
            $config = DB::table('system_configs')->first();
            
            return response()->json([
                'status' => 'success',
                'data' => $config
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil config: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan pesanan awal (Kirim ke Dapur)
     */
    public function storeOrder(Request $request)
    {
        $user = $request->user();
        $outletId = $user->outlet_id;

        if (!$outletId) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 403);
        }

        return DB::transaction(function () use ($request, $outletId, $user) {
            // Ambil PPN terbaru dari config, default 10% jika tidak ada
            $taxConfig = DB::table('system_configs')->value('tax_percentage') ?? 10;

            // Generate Order Number yang aman (Format: POS-TAHUNBULANTANGGAL-RANDOM)
            $orderNumber = 'POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // 1. Simpan Order Utama (Status awal 'pending')
            $order = Order::create([
                'order_number'      => $orderNumber,
                'user_id'           => $user->id,
                'outlet_id'         => $outletId,
                'status'            => 'pending', 
                'subtotal'          => $request->subtotal,
                'tax'               => ($request->subtotal * $taxConfig) / 100,
                'total_price'       => $request->subtotal + (($request->subtotal * $taxConfig) / 100),
                'delivery_fee'      => 0,
                'delivery_address'  => $request->delivery_address ?? $request->customer_name ?? 'Pelanggan POS',
            ]);

            // 2. Simpan Detail Item
            foreach ($request->items as $item) {
                OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'         => $item['id'],
                    'quantity'           => $item['quantity'],
                    'product_name_snap'  => $item['name'] ?? $item['product_name_snap'],
                    'product_price_snap' => $item['price'] ?? $item['product_price_snap'],
                    'subtotal'           => ($item['price'] ?? $item['product_price_snap']) * $item['quantity'],
                ]);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Pesanan berhasil dikirim ke dapur',
                'data'    => $order
            ]);
        });
    }

    /**
     * Finalisasi Pembayaran (Ubah status pending -> completed)
     */
    public function completePayment(Request $request, $id)
    {
        try {
            $user = $request->user();

            return DB::transaction(function () use ($request, $id, $user) {
                $order = Order::where('id', $id)
                    ->where('outlet_id', $user->outlet_id)
                    ->first();

                if (!$order) {
                    return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
                }

                // Update status HANYA untuk kolom yang pasti ada (status dan payment_method)
                // Jika error masih terjadi setelah ini, berarti tabel 'orders' kamu tidak memiliki kolom 'payment_method'
                $order->update([
                    'status' => 'completed',
                    'payment_method' => $request->payment_method ?? 'tunai',
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Pembayaran berhasil diproses',
                    'data' => $order->load('items') // Load items untuk struk final
                ]);
            });
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }
}