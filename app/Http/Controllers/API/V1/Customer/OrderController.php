<?php

namespace App\Http\Controllers\API\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Outlet;
use App\Services\Order\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Tambahkan ini untuk debug db transaction jika perlu

class OrderController extends Controller
{
    use ApiResponse;

    protected OrderService $orderService;

    // [PERBAIKAN 1] Gunakan Dependency Injection
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Create a new order
     */
    public function store(Request $request)
    {
        // Validasi Input
        $validated = $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_snap' => 'nullable|array',
            'delivery_address' => 'required|string',
            'delivery_latitude' => 'nullable|numeric',
            'delivery_longitude' => 'nullable|numeric',
            'distance_real' => 'nullable|numeric|min:0',
        ]);

        // [DEBUG] Cek apakah validasi bisnis logic lulus
        $validation = $this->orderService->validateCheckout($validated['outlet_id'], $validated['items']);
        
        if (!$validation['valid']) {
            return $this->errorResponse($validation['message'], 400);
        }

        try {
            // Tambahkan User ID dari Token yang sedang login
            $validated['user_id'] = auth()->id();
            
            // Pisahkan items dari data order utama
            $items = $validated['items'];
            
            // Hapus items dari array validated agar tidak error saat insert ke tabel orders
            unset($validated['items']);

            // Panggil Service
            $order = $this->orderService->createOrder($validated, $items);

            return $this->successResponse(
                $order->load('items', 'outlet'), 
                'Order created successfully', 
                201
            );

        } catch (\Exception $e) {
            // [PENTING] Ini akan memberitahumu kenapa error 500 terjadi
            return $this->errorResponse('Server Error: ' . $e->getMessage() . ' on line ' . $e->getLine(), 500);
        }
    }

    /**
     * Get customer's orders
     */
    public function index(Request $request)
    {
        $orders = Order::where('user_id', auth()->id())
            ->with(['items.product', 'outlet']) // Load product detail juga biar lengkap
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->successResponse($orders, 'Orders retrieved successfully');
    }

    /**
     * Get single order
     */
    public function show(Order $order)
    {
        // Pastikan order milik user yang login
        if ($order->user_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized access to this order', 403);
        }

        $order->load(['items.product', 'outlet', 'driver']);

        return $this->successResponse($order, 'Order retrieved successfully');
    }

    /**
     * Cancel order
     */
    public function cancel(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Cek logic di Model Order apakah status membolehkan cancel
        if (!$order->canBeCancelled()) {
            return $this->errorResponse('Order cannot be cancelled at this status (e.g. cooking or delivering)', 400);
        }

        try {
            $this->orderService->cancelOrder($order);
            return $this->successResponse($order->refresh(), 'Order cancelled successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Validate checkout
     */
    public function validateCheckout(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $validation = $this->orderService->validateCheckout($validated['outlet_id'], $validated['items']);

        if (!$validation['valid']) {
            return $this->errorResponse($validation['message'], 400);
        }

        return $this->successResponse($validation, 'Validation passed');
    }

    /**
     * Get outlet products
     */
    public function getOutletProducts(Outlet $outlet)
    {
        try {
            $products = $outlet->products()
                ->wherePivot('is_available', true)
                ->withPivot('is_available', 'custom_price')
                ->get()
                ->map(function ($product) {
                    // Logic harga: Gunakan harga custom outlet jika ada
                    if (isset($product->pivot->custom_price) && $product->pivot->custom_price > 0) {
                        $product->price = $product->pivot->custom_price;
                    }
                    return $product;
                });

            return $this->successResponse($products, 'Products retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memuat produk: ' . $e->getMessage(), 500);
        }
    }
}