<?php

namespace App\Http\Controllers\API\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $tables = DiningTable::where('outlet_id', $user->outlet_id)
            ->withCount(['orders as open_orders_count' => function ($q) {
                $q->where('order_type', 'dine_in')->whereNull('paid_at')->where('status', '!=', 'cancelled');
            }])
            ->orderBy('name')
            ->get();

        return $this->successResponse($tables, 'Tables retrieved successfully');
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:50',
        ]);

        $table = DiningTable::create([
            'outlet_id' => $user->outlet_id,
            'name' => $validated['name'],
            'capacity' => $validated['capacity'] ?? null,
        ]);

        return $this->successResponse($table, 'Meja berhasil dibuat', 201);
    }

    public function update(Request $request, DiningTable $table)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || $table->outlet_id !== $user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:50',
        ]);

        $table->update($validated);

        return $this->successResponse($table, 'Meja berhasil diperbarui');
    }

    public function destroy(DiningTable $table)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || $table->outlet_id !== $user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $table->delete();

        return $this->successResponse(null, 'Meja berhasil dihapus');
    }

    public function regenerateQr(DiningTable $table)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || $table->outlet_id !== $user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $table->regenerateToken();

        return $this->successResponse($table, 'QR code berhasil diperbarui');
    }

    /**
     * List unpaid dine-in orders for a table (the open running bill)
     */
    public function bill(DiningTable $table)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || $table->outlet_id !== $user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $orders = $table->orders()
            ->where('order_type', 'dine_in')
            ->whereNull('paid_at')
            ->where('status', '!=', 'cancelled')
            ->with('items')
            ->orderBy('created_at')
            ->get();

        return $this->successResponse([
            'orders' => $orders,
            'total' => $orders->sum('total_price'),
        ], 'Bill retrieved successfully');
    }

    /**
     * Close out a table's running bill: mark all its unpaid dine-in orders as paid
     */
    public function closeBill(DiningTable $table)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || $table->outlet_id !== $user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $table->orders()
            ->where('order_type', 'dine_in')
            ->whereNull('paid_at')
            ->where('status', '!=', 'cancelled')
            ->update(['paid_at' => now(), 'status' => 'completed', 'completed_at' => now()]);

        return $this->successResponse(null, 'Tagihan meja berhasil ditutup');
    }
}
