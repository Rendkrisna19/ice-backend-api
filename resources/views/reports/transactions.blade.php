<!DOCTYPE html>
<html>
<head>
    <title>Laporan Keuangan</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #15423C; }
        .header p { margin: 5px 0; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #e5e5e5; padding: 10px; text-align: left; }
        th { background-color: #15423C; color: white; text-transform: uppercase; font-size: 10px; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .footer-total { background-color: #f8fafc; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Transaksi & Keuangan</h2>
        <p>Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID Order</th>
                <th>Waktu</th>
                <th>Merchant</th>
                <th class="text-right">Total Gross</th>
                <th class="text-right">App Fee</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $order)
            <tr>
                <td class="font-bold">#{{ $order->order_number ?? $order->id }}</td>
                <td>{{ \Carbon\Carbon::parse($order->created_at)->format('d M y, H:i') }}</td>
                <td>{{ $order->outlet->name ?? '-' }}</td>
                <td class="text-right">Rp {{ number_format($order->total_price, 0, ',', '.') }}</td>
                <td class="text-right text-green-600">
                    +Rp {{ number_format(($order->delivery_fee ?? 0) + ($order->tax ?? 0), 0, ',', '.') }}
                </td>
                <td>{{ strtoupper($order->status) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="footer-total">
            <tr>
                <td colspan="3" class="text-right font-bold">TOTAL PENDAPATAN BRUTO</td>
                <td class="text-right font-bold" style="color: #15423C;">Rp {{ number_format($totalGross, 0, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>