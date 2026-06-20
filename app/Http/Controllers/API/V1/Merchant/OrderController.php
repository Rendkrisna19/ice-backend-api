<?php

namespace App\Http\Controllers\API\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Order\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    protected OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    /**
     * Get all orders for merchant's outlet
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $orders = Order::where('outlet_id', $user->outlet_id)
            ->with('items', 'customer', 'driver', 'table')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->successResponse($orders, 'Orders retrieved successfully');
    }

    /**
     * Get single order
     */
    public function show(Order $order)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || $order->outlet_id !== $user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $order->load('items', 'customer', 'driver', 'outlet');

        return $this->successResponse($order, 'Order retrieved successfully');
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string'
        ]);

        $user = auth()->user();

        // LOGIC PERBAIKAN:
        // Cari order berdasarkan ID saja dulu
        $query = Order::where('id', $id);

        // Jika user BUKAN admin, harus dicek outlet-nya
        if ($user->role !== 'admin') {
            if (!$user->outlet_id) {
                return response()->json(['message' => 'Unauthorized: No outlet assigned'], 403);
            }
            $query->where('outlet_id', $user->outlet_id);
        }

        $order = $query->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found or access denied'], 404);
        }

        // Update Status
        $order->status = $request->status;
        if ($request->status === 'completed') {
            $order->completed_at = now();
        }
        $order->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status berhasil diperbarui',
            'data' => $order
        ]);
    }

    public function rejectOrder(Request $request, $id)
    {
        // 1. Validasi Alasan
        $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $user = auth()->user();

        // 2. Cari Order
        $order = Order::find($id);

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        // 3. FIX ERROR 403 (Unauthorized)
        // Kita izinkan akses jika:
        // a. User adalah ADMIN (untuk testing/override)
        // b. ATAU User adalah KASIR pemilik outlet yang sama
        if ($user->role !== 'admin') {
            if ($user->outlet_id !== $order->outlet_id) {
                return $this->errorResponse('Unauthorized: Outlet tidak cocok', 403);
            }
        }

        // 4. Validasi Status (Hanya Pending yang bisa ditolak di sini)
        // Jika statusnya sudah 'on_delivery' atau 'completed', tidak bisa ditolak
        if ($order->status !== 'pending') {
            return $this->errorResponse('Hanya pesanan baru masuk (pending) yang bisa ditolak.', 400);
        }

        // 5. Update Status jadi Cancelled
        $order->update([
            'status' => 'cancelled',
            // Pastikan kolom ini ada di database, kalau tidak ada silakan dihapus barisnya
            // 'cancel_reason' => $request->reason 
        ]);

        return $this->successResponse($order, 'Pesanan berhasil ditolak.');
    }
    /**
     * Get available drivers for Dropdown
     */
    public function getAvailableDrivers(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Ambil semua driver yang sedang online, tidak peduli outlet mana
        $drivers = User::where('role', 'driver')
            ->where('is_online', true)
            ->select('id', 'name', 'email', 'is_online', 'is_busy', 'outlet_id')
            ->orderBy('name', 'asc')
            ->get();

        return $this->successResponse($drivers, 'Available drivers retrieved successfully');
    }

    /**
     * Assign driver to order
     */
   /**
     * Assign Driver (Panggil Driver)
     * Ini method yang kamu cari!
     */
   public function assignDriver(Request $request, $id)
    {
        // 1. Validasi
        $request->validate([
            'driver_id' => 'required|exists:users,id'
        ]);

        $user = auth()->user();

        // 2. Cari Order
        $order = Order::where('id', $id)
            ->where('outlet_id', $user->outlet_id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // 3. Cari Driver & Validasi
        $driver = User::where('id', $request->driver_id)
            ->where('role', 'driver')
            ->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver invalid'], 400);
        }

        // (Opsional) Jika kamu ingin membatasi max order per driver (misal max 3), 
        // kamu bisa mengaktifkan pengecekan ini:
        /*
        $activeOrdersCount = Order::where('driver_id', $driver->id)
                                  ->where('status', 'on_delivery')
                                  ->count();
                                  
        if ($activeOrdersCount >= 3) {
            return response()->json(['message' => 'Driver ini sudah membawa batas maksimal pesanan'], 422);
        }
        */

        // 4. Update Order
        $order->update([
            'driver_id' => $driver->id,
            'status' => 'on_delivery', 
            'picked_up_at' => now(), 
        ]);

        // Catatan: Kita TIDAK mengubah $driver->update(['is_busy' => true]) 
        // agar driver tetap bisa dipilih lagi oleh merchant untuk orderan lain yang searah.

        return response()->json([
            'status' => 'success',
            'message' => 'Driver assigned successfully',
            'data' => $order->load('driver') 
        ]);
    }

    /**
     * Toggle menu availability
     */
    public function toggleMenuAvailability(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'is_available' => 'required|boolean',
        ]);

        $outlet = Outlet::find($user->outlet_id);
        $outlet->products()->updateExistingPivot(
            $validated['product_id'],
            ['is_available' => $validated['is_available']]
        );

        return $this->successResponse(null, 'Menu availability updated successfully');
    }

    /**
     * Get outlet details with status
     */
    public function getOutletStatus(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $outlet = Outlet::find($user->outlet_id)->load('users', 'products');

        $now = now();
        $isOpen = $now->format('H:i:s') >= $outlet->opening_hour->format('H:i:s') &&
            $now->format('H:i:s') <= $outlet->closing_hour->format('H:i:s') &&
            !$outlet->is_force_closed;

        $outlet->is_currently_open = $isOpen;
        $outlet->opening_hour_str = $outlet->opening_hour->format('H:i');
        $outlet->closing_hour_str = $outlet->closing_hour->format('H:i');

        return $this->successResponse($outlet, 'Outlet status retrieved successfully');
    }

    public function toggleOutletStatus(Request $request)
    {
        // 1. Validasi Input (Frontend mengirim true/false)
        $request->validate([
            'is_open' => 'required|boolean'
        ]);

        $user = auth()->user();

        // 2. Cek Hak Akses (Sesuaikan dengan logic getOutletStatus kamu)
        if (!$user->outlet_id) {
             return $this->errorResponse('Unauthorized: No outlet assigned', 403);
        }

        $outlet = Outlet::find($user->outlet_id);

        if (!$outlet) {
            return $this->errorResponse('Outlet not found', 404);
        }

        // 3. Update Database
        // LOGIC: 
        // Jika Frontend minta BUKA (is_open = true) -> maka is_force_closed harus FALSE
        // Jika Frontend minta TUTUP (is_open = false) -> maka is_force_closed harus TRUE
        $outlet->is_force_closed = !$request->is_open;
        $outlet->save();

        // 4. Return Response
        $statusMsg = $request->is_open ? 'DIBUKA' : 'DITUTUP';

        return $this->successResponse([
            'is_currently_open' => $request->is_open,
            'is_force_closed' => (bool) $outlet->is_force_closed
        ], "Toko berhasil $statusMsg");
    }

    /**
     * Force close outlet
     */
    public function forceClose(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        Outlet::find($user->outlet_id)->update(['is_force_closed' => true]);

        return $this->successResponse(null, 'Outlet force closed successfully');
    }

    /**
     * Force open outlet
     */
    public function forceOpen(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        Outlet::find($user->outlet_id)->update(['is_force_closed' => false]);

        return $this->successResponse(null, 'Outlet force opened successfully');
    }

    public function getKanbanOrders(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Ambil hanya status yang aktif (Bukan history)
        $activeStatuses = ['pending', 'paid', 'preparing', 'ready', 'on_delivery'];

        $orders = Order::where('outlet_id', $user->outlet_id)
            ->whereIn('status', $activeStatuses)
            ->with(['items', 'customer', 'driver' => function ($q) {
                // UBAH 'phone' JADI 'phone_number'
                $q->select('id', 'name', 'phone', 'plate_number');
            }])
            ->orderBy('created_at', 'asc') // Yang lama di atas (Urgent)
            ->get();

        // Grouping by status untuk mempermudah Frontend
        $kanbanData = [
            'pending' => $orders->whereIn('status', ['pending', 'paid'])->values(), // Baru Masuk
            'preparing' => $orders->where('status', 'preparing')->values(),         // Dapur
            'ready' => $orders->where('status', 'ready')->values(),                 // Siap Antar
            'on_delivery' => $orders->where('status', 'on_delivery')->values(),     // Sedang Diantar
        ];

        return $this->successResponse($kanbanData, 'Kanban orders retrieved successfully');
    }

    /**
     * Get Order Counts for Badges
     * Untuk mengisi angka notifikasi di tab/header
     */
    public function getOrderCounts()
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $counts = Order::where('outlet_id', $user->outlet_id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Format default 0 jika tidak ada data
        $result = [
            'pending' => ($counts['pending'] ?? 0) + ($counts['paid'] ?? 0),
            'preparing' => $counts['preparing'] ?? 0,
            'ready' => $counts['ready'] ?? 0,
            'on_delivery' => $counts['on_delivery'] ?? 0,
            'completed' => $counts['completed'] ?? 0,
            'cancelled' => $counts['cancelled'] ?? 0,
        ];

        return $this->successResponse($result, 'Order counts retrieved successfully');
    }

    /**
     * Accept Order (Move to Preparing)
     * Khusus untuk memindahkan dari "Baru Masuk" ke "Dapur"
     */
    public function acceptOrder(Request $request, Order $order)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || $order->outlet_id !== $user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Pastikan order masih pending/paid sebelum masuk dapur
        if (!in_array($order->status, ['pending', 'paid'])) {
            return $this->errorResponse('Order cannot be accepted directly from current status', 400);
        }

        // Update status via service (agar logic log/notif jalan)
        $this->orderService->updateStatus($order, 'preparing');

        // Optional: Print struk dapur otomatis disini

        return $this->successResponse($order->refresh(), 'Order accepted and sent to kitchen');
    }
}
