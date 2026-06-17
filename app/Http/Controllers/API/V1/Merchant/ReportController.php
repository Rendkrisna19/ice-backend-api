<?php

namespace App\Http\Controllers\API\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    use ApiResponse;

    private function getReportData($date, $outlet_id)
    {
        $baseQuery = Order::where('outlet_id', $outlet_id)
            ->whereDate('created_at', $date);

        $totalOrders = (clone $baseQuery)->count();
        $completedOrders = (clone $baseQuery)->whereIn('status', ['completed', 'delivered'])->count();
        $successPercentage = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100) : 0;

        $transactions = (clone $baseQuery)
            ->with(['customer', 'items.product', 'driver'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                $isPosOrder = str_starts_with($order->order_number ?? '', 'POS-');
                $modal = 0;
                $summary = $order->items->map(function ($item) use (&$modal) {
                    $name = $item->product_name_snap ?? $item->name ?? 'Produk';
                    $qty = $item->quantity ?? 1;
                    $costPrice = $item->product ? $item->product->cost_price : 0;
                    $modal += ($costPrice * $qty);
                    return $name . ' x' . $qty;
                })->implode(', ');

                $revenue = $order->subtotal;
                $netProfit = $revenue - $modal;

                // POS orders: nama pelanggan ada di delivery_address, bukan di relasi customer
                $customerName = $isPosOrder
                    ? ($order->delivery_address ?? 'Pelanggan POS')
                    : ($order->customer->name ?? 'Guest');

                // POS orders: tidak ada driver
                $driverName = $isPosOrder ? 'POS (Kasir)' : ($order->driver->name ?? '-');

                return [
                    'id' => $order->id,
                    'time' => $order->created_at->format('H:i'),
                    'order_number' => $order->order_number,
                    'customer_name' => $customerName,
                    'summary' => $summary ?: '-',
                    'total' => $order->total_price,
                    'revenue' => $revenue,
                    'modal' => $modal,
                    'net_profit' => $netProfit,
                    'delivery_fee' => $order->delivery_fee ?? 0,
                    'driver_name' => $driverName,
                    'status' => $order->status,
                    'source' => $isPosOrder ? 'pos' : 'online',
                ];
            });

        // Sum for completed or delivered orders
        $validTransactions = $transactions->filter(function($trx) {
            return in_array($trx['status'], ['completed', 'delivered']);
        });

        $totalRevenue = $validTransactions->sum('revenue');
        $totalModal = $validTransactions->sum('modal');
        $totalNetProfit = $validTransactions->sum('net_profit');
        $totalDeliveryFee = $validTransactions->sum('delivery_fee');

        return [
            'summary' => [
                'date' => $date,
                'total_revenue' => $totalRevenue,
                'total_modal' => $totalModal,
                'net_profit' => $totalNetProfit,
                'total_delivery_fee' => $totalDeliveryFee,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'success_percentage' => $successPercentage,
            ],
            'transactions' => $transactions
        ];
    }

    public function getDailyReport(Request $request)
    {
        $user = auth()->user();
        $date = $request->input('date', Carbon::today()->toDateString());
        $data = $this->getReportData($date, $user->outlet_id);

        return $this->successResponse($data, 'Daily report retrieved successfully');
    }

    public function exportExcel(Request $request)
    {
        $user = auth()->user();
        $date = $request->input('date', Carbon::today()->toDateString());
        $data = $this->getReportData($date, $user->outlet_id);

        $fileName = "Laporan_Penjualan_{$date}.csv";
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Jam', 'Pelanggan', 'Ringkasan Pesanan', 'Total (Rp)', 'Status']);

            foreach ($data['transactions'] as $row) {
                fputcsv($file, [
                    $row['time'],
                    $row['customer_name'],
                    $row['summary'],
                    $row['total'],
                    strtoupper($row['status'])
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $user = auth()->user();
        $date = $request->input('date', Carbon::today()->toDateString());
        $data = $this->getReportData($date, $user->outlet_id);

        $html = '
            <style>
                body { font-family: sans-serif; font-size: 14px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #15423C; color: white; }
                .text-right { text-align: right; }
            </style>
            <h2>Laporan Penjualan Harian</h2>
            <p><strong>Tanggal:</strong> ' . date('d F Y', strtotime($date)) . '</p>
            <p><strong>Total Omzet:</strong> Rp ' . number_format($data['summary']['total_revenue'], 0, ',', '.') . '</p>
            <p><strong>Total Pesanan:</strong> ' . $data['summary']['total_orders'] . ' (' . $data['summary']['success_percentage'] . '% Sukses)</p>
            <table>
                <thead>
                    <tr>
                        <th>Jam</th><th>Pelanggan</th><th>Ringkasan Pesanan</th><th>Total</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($data['transactions'] as $trx) {
            $html .= '<tr>
                        <td>' . $trx['time'] . '</td>
                        <td>' . $trx['customer_name'] . '</td>
                        <td>' . $trx['summary'] . '</td>
                        <td class="text-right">Rp ' . number_format($trx['total'], 0, ',', '.') . '</td>
                        <td>' . strtoupper($trx['status']) . '</td>
                      </tr>';
        }

        $html .= '</tbody></table>';

        $pdf = Pdf::loadHTML($html);
        return $pdf->download("Laporan_Penjualan_{$date}.pdf");
    }
}