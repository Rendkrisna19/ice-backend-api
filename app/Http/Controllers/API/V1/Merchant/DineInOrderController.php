<?php

namespace App\Http\Controllers\API\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DineInOrderController extends Controller
{
    use ApiResponse;

    private const ACTIVE_STATUSES = ['pending', 'preparing', 'ready', 'completed'];

    public function kanban(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $orders = Order::where('outlet_id', $user->outlet_id)
            ->where('order_type', 'dine_in')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->with('items', 'table')
            ->orderBy('created_at', 'asc')
            ->get();

        $kanbanData = [
            'pending' => $orders->where('status', 'pending')->values(),
            'preparing' => $orders->where('status', 'preparing')->values(),
            'ready' => $orders->where('status', 'ready')->values(),
            'completed' => $orders->where('status', 'completed')->values(),
        ];

        return $this->successResponse($kanbanData, 'Dine-in kanban orders retrieved successfully');
    }

    public function counts()
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $counts = Order::where('outlet_id', $user->outlet_id)
            ->where('order_type', 'dine_in')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $result = [
            'pending' => $counts['pending'] ?? 0,
            'preparing' => $counts['preparing'] ?? 0,
            'ready' => $counts['ready'] ?? 0,
            'completed' => $counts['completed'] ?? 0,
            'cancelled' => $counts['cancelled'] ?? 0,
        ];

        return $this->successResponse($result, 'Dine-in order counts retrieved successfully');
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:preparing,ready,completed,cancelled',
        ]);

        $user = auth()->user();

        if (!$user->outlet_id) {
            return $this->errorResponse('Unauthorized: No outlet assigned', 403);
        }

        $order = Order::where('id', $id)
            ->where('outlet_id', $user->outlet_id)
            ->where('order_type', 'dine_in')
            ->first();

        if (!$order) {
            return $this->errorResponse('Order not found or access denied', 404);
        }

        $order->status = $request->status;
        if ($request->status === 'completed') {
            $order->completed_at = now();
        }
        $order->save();

        return $this->successResponse($order->load('items', 'table'), 'Status pesanan berhasil diperbarui');
    }
}
