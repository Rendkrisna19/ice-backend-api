<?php

namespace App\Exports;

use App\Models\Order;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable; // <-- Tambahan Import
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Color;

class TransactionsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    use Exportable; // <-- Tambahan Trait agar bisa langsung didownload

    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate) {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection() {
        return Order::with(['customer', 'outlet'])
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function map($order): array {
        $appFee = ($order->delivery_fee ?? 0) + ($order->tax ?? 0);
        return [
            $order->order_number ?? $order->id,
            Carbon::parse($order->created_at)->format('d M Y, H:i'),
            $order->outlet ? $order->outlet->name : '-',
            $order->customer ? $order->customer->name : 'Guest',
            $order->total_price,
            $appFee,
            strtoupper($order->status)
        ];
    }

    public function headings(): array {
        return [
            'ID ORDER', 'WAKTU TRANSAKSI', 'MERCHANT', 'PELANGGAN', 'TOTAL GROSS (Rp)', 'APP FEE (Rp)', 'STATUS'
        ];
    }

    // Memberikan warna background hijau gelap dan teks putih tebal di Header
    public function styles(Worksheet $sheet) {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF15423C']]
            ],
        ];
    }
}