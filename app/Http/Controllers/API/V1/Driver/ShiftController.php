<?php

namespace App\Http\Controllers\API\V1\Driver;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ShiftController extends Controller
{
    use ApiResponse;

    /**
     * Clock in driver (Mulai Shift)
     */
    public function clockIn(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'driver') {
            return $this->errorResponse('Unauthorized', 403);
        }

        $user->update(['is_online' => true]);

        return $this->successResponse(['is_online' => true], 'Driver clocked in successfully');
    }

    /**
     * Clock out driver (Selesai Shift)
     */
    public function clockOut(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'driver') {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Cek apakah masih ada order aktif (selain completed/cancelled/delivered)
        $activeOrders = Order::where('driver_id', $user->id)
            ->whereIn('status', ['pending', 'paid', 'preparing', 'ready', 'on_delivery'])
            ->count();

        if ($activeOrders > 0) {
            return $this->errorResponse('Selesaikan semua order sebelum clock out!', 400);
        }

        $user->update(['is_online' => false, 'is_busy' => false]);

        return $this->successResponse(['is_online' => false], 'Driver clocked out successfully');
    }

    /**
     * Get All Assigned Orders (Histori & Aktif)
     */
   public function getAssignedOrders(Request $request)
    {
        $user = auth()->user();

        $orders = Order::where('driver_id', $user->id)
            ->with(['items', 'customer', 'outlet'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->successResponse($orders, 'Assigned orders retrieved');
    }

    /**
     * Get Active Job (Order yang sedang berjalan saat ini)
     * Helper untuk frontend agar driver langsung fokus ke order aktif.
     */
   public function getActiveJobs(Request $request)
    {
        $user = auth()->user();

        $activeOrders = Order::where('driver_id', $user->id)
            ->whereIn('status', ['ready', 'on_delivery'])
            ->with(['items', 'customer', 'outlet'])
            ->orderBy('created_at', 'asc') 
            ->get();

        return $this->successResponse($activeOrders, 'Active jobs retrieved');
    }

    /**
     * Start Delivery (Driver Ambil Barang di Resto & Jalan)
     * Mengubah status dari 'ready' -> 'on_delivery'
     */
    public function startDelivery(Request $request, Order $order)
    {
        $user = auth()->user();

        if ($order->driver_id !== $user->id) {
            return $this->errorResponse('Order ini bukan tugas Anda', 403);
        }

        if ($order->status !== 'ready') {
            return $this->errorResponse('Order belum siap atau status tidak valid untuk dimulai', 400);
        }

        $order->update([
            'status' => 'on_delivery',
            'picked_up_at' => now(),
        ]);

        return $this->successResponse($order, 'Delivery started. Safe trip!');
    }

    /**
     * Complete Delivery & Upload Proof
     * Mengubah status 'on_delivery' -> 'delivered'
     * Wajib upload foto bukti.
     */
    public function completeDelivery(Request $request, Order $order)
    {
        $user = auth()->user();

        if ($user->role !== 'driver' || $order->driver_id !== $user->id) {
            return $this->errorResponse('Unauthorized access to this order', 403);
        }

        if ($order->status !== 'on_delivery') {
            return $this->errorResponse('Order belum dalam pengantaran', 400);
        }

        $request->validate([
            'proof_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $path = null;
        if ($request->hasFile('proof_image')) {
            $file = $request->file('proof_image');
            $filename = 'proof_' . $order->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('delivery_proofs', $filename, 'public');
        }

        if (!$path) {
            return $this->errorResponse('Gagal mengupload bukti foto', 500);
        }

        $order->update([
            'status' => 'delivered', 
            'proof_of_delivery' => url('storage/' . $path),
            'delivered_at' => now(),
        ]);

        // Cleanup driver_locations for this order
        \App\Models\DriverLocation::where('order_id', $order->id)->delete();

        // Tambahkan ongkir (delivery_fee) ke saldo (wallet_balance) driver
        $user->wallet_balance = ($user->wallet_balance ?? 0) + $order->delivery_fee;
        $user->save();

        return $this->successResponse($order, 'Bukti terupload. Order selesai diantar. Saldo ditambahkan.');
    }
    /**
     * Get driver status dashboard
     */
    public function getStatus(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $completedToday = Order::where('driver_id', $user->id)
                ->whereIn('status', ['delivered', 'completed'])
                ->whereDate('updated_at', now())
                ->count();

            $activeJobsCount = Order::where('driver_id', $user->id)
                ->whereIn('status', ['ready', 'on_delivery'])
                ->count();

            $phone = $user->phone ?? '-'; 
            $plate = $user->plate_number ?? '-';
            $wallet = isset($user->wallet_balance) ? (float) $user->wallet_balance : 0;

            return $this->successResponse([
                'is_online' => (bool) $user->is_online,
                'is_busy' => $activeJobsCount > 0,
                'active_jobs_count' => $activeJobsCount,
                'completed_today' => $completedToday,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $phone, 
                'plate_number' => $plate,
                'wallet_balance' => $wallet,
                'profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
                'rating' => 4.8, 
                'join_date' => $user->created_at ? $user->created_at->format('Y') : date('Y'),
            ], 'Driver status retrieved');

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function changePassword(Request $request) {
    $request->validate([
        'current_password' => 'required',
        'new_password' => 'required|min:8|confirmed',
    ]);

    $user = auth()->user();

    // Validasi apakah password lama sesuai
    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json(['message' => 'Password saat ini salah.'], 400);
    }

    $user->update([
        'password' => Hash::make($request->new_password)
    ]);

    return response()->json(['message' => 'Password berhasil diubah']);
}
}