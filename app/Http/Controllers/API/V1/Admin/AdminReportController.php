<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminReportController extends Controller
{
    use ApiResponse;

    public function getAnalytics(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $outletId = $request->input('outlet_id', 'all');

        $query = Order::with(['items.product', 'user', 'outlet', 'driver'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('order_number', 'not like', 'POS-%');

        if ($outletId !== 'all') {
            $query->where('outlet_id', $outletId);
        }

        $allOrders = $query->get();

        $completedOrders = $allOrders->filter(function($order) {
            return in_array($order->status, ['completed', 'delivered']);
        });

        $refundOrders = $allOrders->filter(function($order) {
            return in_array($order->status, ['cancelled', 'refunded', 'refund_needed']);
        });

        $grossSales = 0;
        $cogs = 0;
        $totalItemsSold = 0;
        $platformFeeTotal = 0; // Assume admin income comes from platform fee or similar

        foreach ($completedOrders as $order) {
            $grossSales += $order->subtotal;
            $platformFeeTotal += 2000; // Platform fee per order is Rp 2000 as set in config
            foreach ($order->items as $item) {
                $qty = $item->quantity ?? 1;
                $cost = $item->product ? $item->product->cost_price : 0;
                $cogs += ($cost * $qty);
                $totalItemsSold += $qty;
            }
        }

        $grossProfit = $grossSales - $cogs;
        $grossMarginPercent = $grossSales > 0 ? ($grossProfit / $grossSales) * 100 : 0;
        $revenue = $completedOrders->sum('total_price');
        $refundAmount = $refundOrders->sum('total_price');

        // Recent Orders mapping
        $recentOrders = $allOrders->sortByDesc('created_at')->take(20)->map(function ($order) {
            $modal = 0;
            foreach ($order->items as $item) {
                $modal += ($item->product ? $item->product->cost_price : 0) * ($item->quantity ?? 1);
            }
            
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'created_at' => $order->created_at->toISOString(),
                'total_price' => $order->total_price,
                'delivery_fee' => $order->delivery_fee ?? 0,
                'tax' => $order->tax ?? 0,
                'app_fee_est' => 2000,
                'gross_sales_est' => $order->subtotal,
                'cogs_est' => $modal,
                'gross_profit_est' => $order->subtotal - $modal,
                'status' => $order->status,
                'user' => ['name' => $order->customer->name ?? 'Guest'],
                'outlet' => ['name' => $order->outlet->name ?? 'Unknown'],
            ];
        })->values();

        $chartData = [];
        $groupedOrders = $completedOrders->groupBy(function($order) {
            return Carbon::parse($order->created_at)->format('Y-m-d');
        });

        foreach ($groupedOrders as $date => $orders) {
            $dailyGrossSales = $orders->sum('subtotal');
            $dailyCogs = 0;
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $dailyCogs += ($item->product ? $item->product->cost_price : 0) * ($item->quantity ?? 1);
                }
            }
            $chartData[] = [
                'date' => $date,
                'gross' => $orders->sum('total_price'),
                'net' => $orders->count() * 2000,
                'gross_sales' => $dailyGrossSales,
                'cogs' => $dailyCogs,
                'gross_profit' => $dailyGrossSales - $dailyCogs,
            ];
        }

        // Sort chartData by date
        usort($chartData, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        $data = [
            'summary' => [
                'revenue' => ['value' => $revenue, 'growth' => 0],
                'net_income' => ['value' => $platformFeeTotal, 'growth' => 0],
                'orders' => ['value' => $completedOrders->count(), 'growth' => 0],
                'refund' => ['value' => $refundAmount, 'growth' => 0],
                'gross_sales' => ['value' => $grossSales, 'growth' => 0],
                'cogs' => ['value' => $cogs, 'growth' => 0],
                'gross_profit' => ['value' => $grossProfit, 'growth' => 0],
                'gross_margin_percent' => ['value' => $grossMarginPercent, 'growth' => null],
                'total_items_sold' => ['value' => $totalItemsSold, 'growth' => null],
            ],
            'unit_economics' => [
                'gross_sales' => $grossSales,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'gross_margin_percent' => $grossMarginPercent,
                'app_fee_income' => $platformFeeTotal,
                'combined_income' => $platformFeeTotal,
                'total_items_sold' => $totalItemsSold,
            ],
            'applied_filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'outlet_id' => $outletId === 'all' ? null : (int)$outletId,
                'outlet_mode' => $outletId === 'all' ? 'all_outlets' : 'single_outlet',
            ],
            'chart' => $chartData,
            'recent_orders' => $recentOrders,
        ];

        return $this->successResponse($data);
    }

    public function exportExcel(Request $request) {
        return response()->json(['message' => 'Export not implemented yet. Use frontend export.'], 200);
    }

    public function exportPdf(Request $request) {
        return response()->json(['message' => 'Export not implemented yet. Use frontend export.'], 200);
    }
}
