<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\SystemConfig;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str; // PENTING: Import Str
use App\Services\DeliveryService; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Exports\TransactionsExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;





class ManagementController extends Controller
{
    use ApiResponse;

  public function getRefundOrders(Request $request)
    {
        // Ambil order yang statusnya 'refund_needed'
        // Load user agar nama user muncul di tabel frontend
        $orders = Order::where('status', 'refund_needed')
            ->with(['items', 'user', 'outlet']) // 'user' adalah relasi ke customer
            ->orderBy('updated_at', 'desc') // Urutkan dari yang baru ditolak
            ->paginate(10);

        return $this->successResponse($orders);
    }
    

    /**
     * Process Refund (Approve/Reject)
     */
    public function processRefund(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        $order = Order::find($id);

        if (!$order || $order->status !== 'refund_needed') {
            return $this->errorResponse('Order not found or not eligible for refund', 404);
        }

        if ($request->action === 'reject') {
            // Admin menolak refund (misal: penipuan), kembalikan ke completed/cancelled?
            // Atau status khusus 'refund_rejected'
            $order->update(['status' => 'completed']); 
            return $this->successResponse($order, 'Permintaan refund ditolak. Status order diselesaikan.');
        }

        // Action: Approve
        // TODO: Masukkan Logic Payment Gateway Refund disini (Xendit/Stripe/Midtrans)
        
        $order->update(['status' => 'refunded']);

        return $this->successResponse($order, 'Refund disetujui & status diperbarui.');
    }

    /**
     * Get List of Rejected/Cancelled Orders
     */
    public function getRejectedOrders(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Ambil order dengan status 'cancelled'
        // Kita load 'outlet' agar tahu Merchant mana yang menolak
        $orders = Order::where('status', 'cancelled')
            ->with(['items', 'customer', 'outlet']) 
            ->orderBy('updated_at', 'desc') // Urutkan berdasarkan waktu pembatalan
            ->paginate(10);

        return $this->successResponse($orders, 'Data pesanan ditolak berhasil diambil');
    }
    
    /**
     * Manage outlets - List
     */
    public function listOutlets(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        $outlets = Outlet::with('users')
            ->paginate(20);

        return $this->successResponse($outlets, 'Outlets retrieved successfully');
    }

      public function getOutlets(Request $request)
    {
        // Ambil data outlet beserta relasi owner-nya (User)
        // Pagination 10 per halaman
        $outlets = Outlet::with('owner')->latest()->paginate(10);
        return $this->successResponse($outlets);
    }

    /**
     * Create outlet
     */
    public function createOutlet(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:outlets,slug',
            'address' => 'required|string',
            'phone' => 'required|string',
            'whatsapp_number' => 'nullable|string',
            'opening_hour' => 'required',
            'closing_hour' => 'required',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'owner_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        try {
            return DB::transaction(function () use ($request, $validated) {
                
                // 1. Handle File Upload (simpan ke storage/public/outlet)
                $logoPath = null;
                $bannerPath = null;

                if ($request->hasFile('logo')) {
                    $logoPath = $request->file('logo')->store('outlets/logos', 'public');
                    $logoPath = 'storage/' . $logoPath;
                }

                if ($request->hasFile('banner')) {
                    $bannerPath = $request->file('banner')->store('outlets/banners', 'public');
                    $bannerPath = 'storage/' . $bannerPath;
                }

                // 2. Create Outlet
                $outlet = Outlet::create([
                    'name' => $validated['name'],
                    'slug' => $validated['slug'],
                    'address' => $validated['address'],
                    'phone' => $validated['phone'],
                    'whatsapp_number' => $validated['whatsapp_number'] ?? null,
                    'opening_hour' => $validated['opening_hour'],
                    'closing_hour' => $validated['closing_hour'],
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'logo' => $logoPath,
                    'banner' => $bannerPath,
                ]);


                // 3. Create Akun Merchant
                $merchantUser = User::create([
                    'name' => $validated['owner_name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => 'cashier',
                    'outlet_id' => $outlet->id,
                ]);

                return $this->successResponse([
                    'outlet' => $outlet,
                    'merchant_account' => $merchantUser
                ], 'Outlet berhasil dibuat dan menu telah disinkronisasi', 201);
            });

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membuat outlet: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update Outlet (Termasuk Logo & Banner)
     */
    public function updateOutlet(Request $request, Outlet $outlet)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'slug' => 'sometimes|string|unique:outlets,slug,' . $outlet->id,
            'address' => 'sometimes|string',
            'phone' => 'sometimes|string',
            'whatsapp_number' => 'sometimes|nullable|string',
            'opening_hour' => 'sometimes',
            'closing_hour' => 'sometimes',
            'latitude' => 'sometimes|nullable|numeric',
            'longitude' => 'sometimes|nullable|numeric',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Handle File Update (storage public)
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('outlet/logos', 'public');
            $validated['logo'] = 'storage/' . $logoPath;
        }
        if ($request->hasFile('banner')) {
            $bannerPath = $request->file('banner')->store('outlet/banners', 'public');
            $validated['banner'] = 'storage/' . $bannerPath;
        }

        $outlet->update($validated);

        return $this->successResponse($outlet, 'Outlet updated successfully');
    }

    /**
     * Delete Outlet
     */
    public function deleteOutlet(Outlet $outlet)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }


        $outlet->delete();

        return $this->successResponse(null, 'Outlet deleted successfully');
    }
    /**
     * Get system pricing config
     */
    public function getPricingConfig(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        $config = SystemConfig::getCurrent();

        return $this->successResponse($config, 'Pricing config retrieved successfully');
    }

    /**
     * Update system pricing config
     */
    public function updatePricingConfig(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'delivery_base_price' => 'sometimes|numeric|min:0',
            'delivery_base_distance' => 'sometimes|numeric|min:0',
            'delivery_price_per_km' => 'sometimes|numeric|min:0',
            'platform_fee' => 'sometimes|numeric|min:0',
            'tax_percentage' => 'sometimes|numeric|min:0|max:100',
        ]);

        $config = SystemConfig::getCurrent();
        $config->update($validated);

        return $this->successResponse($config, 'Pricing config updated successfully');
    }

    public function simulatePricing(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        $request->validate([
            'distance' => 'required|numeric|min:0', // Jarak dalam KM
            'subtotal' => 'nullable|numeric|min:0', // Subtotal belanja (opsional)
        ]);

        // 1. Ambil Config Saat Ini
        $config = SystemConfig::getCurrent();

        // 2. Ambil parameter input
        $distance = (float) $request->distance;
        $subtotal = (float) ($request->subtotal ?? 0);

        // 3. Logic Hitung Ongkir (Sesuai App\Services\DeliveryService)
        // Formula: BasePrice + Max(0, (Distance - BaseDistance) * PricePerKM)
        
        $basePrice = $config->delivery_base_price;
        $baseDistance = $config->delivery_base_distance;
        $pricePerKm = $config->delivery_price_per_km;
        $taxPercent = $config->tax_percentage;
        $platformFee = $config->platform_fee ?? 0;

        $extraDistance = max(0, $distance - $baseDistance);
        $deliveryFee = $basePrice + ($extraDistance * $pricePerKm);

        // 4. Hitung Pajak & Total
        $taxAmount = ($subtotal * $taxPercent) / 100;
        $grandTotal = $subtotal + $deliveryFee + $taxAmount + $platformFee;

        return $this->successResponse([
            'simulation_input' => [
                'distance_km' => $distance,
                'subtotal' => $subtotal,
            ],
            'calculation_breakdown' => [
                'base_price' => $basePrice,
                'base_distance_quota' => $baseDistance . ' km',
                'extra_distance_charged' => $extraDistance . ' km',
                'price_per_km' => $pricePerKm,
                'delivery_fee_result' => $deliveryFee,
                'platform_fee' => $platformFee,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal
            ]
        ], 'Pricing simulation calculated successfully');
    }

    /**
     * Get reporting data
     */
    public function getReporting(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        $query = Order::where('status', 'completed');

        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->input('outlet_id'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('completed_at', [
                $request->input('start_date'),
                $request->input('end_date'),
            ]);
        }

        $orders = $query->with('items')
            ->get();

        $totalRevenue = $orders->sum('total_price');
        $totalOrders = $orders->count();
        $totalItems = $orders->sum(function ($order) {
            return $order->items->sum('quantity');
        });

        return $this->successResponse([
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'total_items' => $totalItems,
            'average_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
            'orders' => $orders,
        ], 'Reporting data retrieved successfully');
    }

    /**
     * Get Dashboard Analytics & Reports
     */
    public function getAnalytics(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        // 1. FILTER: Rentang Waktu (Default: Bulan Ini)
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $rawOutletId = $request->input('outlet_id'); // Optional filter per merchant

        // Support mode semua merchant: outlet_id bisa null, string kosong, atau "all"
        $isAllOutletMode = is_null($rawOutletId)
            || trim((string) $rawOutletId) === ''
            || strtolower(trim((string) $rawOutletId)) === 'all';

        if (!$isAllOutletMode && !ctype_digit((string) $rawOutletId)) {
            return $this->errorResponse('Invalid outlet_id. Use numeric id or all.', 422);
        }

        $outletId = $isAllOutletMode ? null : (int) $rawOutletId;

        // 2. Waktu Sebelumnya (untuk % vs periode lalu)
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $diffInDays = $start->diffInDays($end) + 1;
        
        $prevStartDate = $start->copy()->subDays($diffInDays)->toDateString();
        $prevEndDate = $start->copy()->subDay()->toDateString();

        // Helper Query Base
        $baseQuery = function($s, $e) use ($outletId) {
            $q = Order::whereBetween('created_at', [$s . ' 00:00:00', $e . ' 23:59:59']);
            if ($outletId) $q->where('outlet_id', $outletId);
            return $q;
        };

        $costSummaryQuery = function($s, $e) use ($outletId) {
            $q = DB::table('orders as o')
                ->leftJoin('order_items as oi', 'o.id', '=', 'oi.order_id')
                ->leftJoin('products as p', 'oi.product_id', '=', 'p.id')
                ->where('o.status', 'completed')
                ->whereBetween('o.created_at', [$s . ' 00:00:00', $e . ' 23:59:59']);

            if ($outletId) {
                $q->where('o.outlet_id', $outletId);
            }

            return $q->selectRaw('
                COALESCE(SUM(oi.subtotal), 0) as gross_sales,
                COALESCE(SUM(oi.quantity * IFNULL(p.cost_price, 0)), 0) as cogs,
                COALESCE(SUM(oi.quantity), 0) as total_items
            ')->first();
        };

        // --- MENGHITUNG 4 INFO BOX ---
        $currentRevenue = $baseQuery($startDate, $endDate)->where('status', 'completed')->sum('total_price');
        $prevRevenue = $baseQuery($prevStartDate, $prevEndDate)->where('status', 'completed')->sum('total_price');

        // Asumsi App Fee = delivery_fee + tax
        $getNetIncome = function($query) {
            return $query->sum(DB::raw('IFNULL(delivery_fee, 0) + IFNULL(tax, 0)')); 
        };
        $currentNet = $getNetIncome($baseQuery($startDate, $endDate)->where('status', 'completed'));
        $prevNet = $getNetIncome($baseQuery($prevStartDate, $prevEndDate)->where('status', 'completed'));

        $currentOrders = $baseQuery($startDate, $endDate)->where('status', 'completed')->count();
        $prevOrders = $baseQuery($prevStartDate, $prevEndDate)->where('status', 'completed')->count();

        $currentRefund = $baseQuery($startDate, $endDate)->whereIn('status', ['cancelled', 'refunded'])->sum('total_price');
        $prevRefund = $baseQuery($prevStartDate, $prevEndDate)->whereIn('status', ['cancelled', 'refunded'])->sum('total_price');

        // Unit economics untuk laporan modal vs penjualan menu
        $currentCostSummary = $costSummaryQuery($startDate, $endDate);
        $prevCostSummary = $costSummaryQuery($prevStartDate, $prevEndDate);

        $currentGrossSales = (float) ($currentCostSummary->gross_sales ?? 0);
        $prevGrossSales = (float) ($prevCostSummary->gross_sales ?? 0);

        $currentCogs = (float) ($currentCostSummary->cogs ?? 0);
        $prevCogs = (float) ($prevCostSummary->cogs ?? 0);

        $currentGrossProfit = $currentGrossSales - $currentCogs;
        $prevGrossProfit = $prevGrossSales - $prevCogs;

        $currentGrossMargin = $currentGrossSales > 0 ? round(($currentGrossProfit / $currentGrossSales) * 100, 2) : 0;
        $currentTotalItems = (int) ($currentCostSummary->total_items ?? 0);

        // --- CHART DATA (TREN PENDAPATAN) ---
        $chartData = $baseQuery($startDate, $endDate)
            ->where('status', 'completed')
            ->selectRaw('DATE(created_at) as date, SUM(total_price) as gross, SUM(IFNULL(delivery_fee, 0) + IFNULL(tax, 0)) as net')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $unitEconomicsChart = DB::table('orders as o')
            ->leftJoin('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'oi.product_id', '=', 'p.id')
            ->where('o.status', 'completed')
            ->whereBetween('o.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($outletId, function($q) use ($outletId) {
                return $q->where('o.outlet_id', $outletId);
            })
            ->selectRaw('DATE(o.created_at) as date, COALESCE(SUM(oi.subtotal), 0) as gross_sales, COALESCE(SUM(oi.quantity * IFNULL(p.cost_price, 0)), 0) as cogs')
            ->groupBy(DB::raw('DATE(o.created_at)'))
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        $chartData = $chartData->map(function ($row) use ($unitEconomicsChart) {
            $costRow = $unitEconomicsChart->get($row->date);
            $grossSales = (float) ($costRow->gross_sales ?? 0);
            $cogs = (float) ($costRow->cogs ?? 0);

            $row->gross_sales = $grossSales;
            $row->cogs = $cogs;
            $row->gross_profit = $grossSales - $cogs;
            return $row;
        });

        // --- RIWAYAT TRANSAKSI TERAKHIR ---
        $recentOrders = Order::with(['customer', 'outlet', 'items.product:id,cost_price'])
            ->when($outletId, function($q) use ($outletId) {
                return $q->where('outlet_id', $outletId);
            })
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->limit(10) // Ambil 10 terbaru untuk dashboard
            ->get()
            ->map(function ($order) {
                // Menambahkan App Fee estimasi ke response agar mudah di-render di tabel
                $order->app_fee_est = ($order->delivery_fee ?? 0) + ($order->tax ?? 0);

                $orderCogs = $order->items->sum(function ($item) {
                    return ($item->quantity ?? 0) * (($item->product->cost_price ?? 0));
                });

                $orderGrossSales = $order->items->sum('subtotal');
                $order->gross_sales_est = (float) $orderGrossSales;
                $order->cogs_est = (float) $orderCogs;
                $order->gross_profit_est = (float) $orderGrossSales - (float) $orderCogs;
                return $order;
            });

        return $this->successResponse([
            'applied_filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'outlet_id' => $outletId,
                'outlet_mode' => $outletId ? 'single_outlet' : 'all_outlets'
            ],
            'summary' => [
                'revenue' => ['value' => $currentRevenue, 'growth' => $this->calculateGrowth($currentRevenue, $prevRevenue)],
                'net_income' => ['value' => $currentNet, 'growth' => $this->calculateGrowth($currentNet, $prevNet)],
                'orders' => ['value' => $currentOrders, 'growth' => $this->calculateGrowth($currentOrders, $prevOrders)],
                'refund' => ['value' => $currentRefund, 'growth' => $this->calculateGrowth($currentRefund, $prevRefund)],
                'gross_sales' => ['value' => $currentGrossSales, 'growth' => $this->calculateGrowth($currentGrossSales, $prevGrossSales)],
                'cogs' => ['value' => $currentCogs, 'growth' => $this->calculateGrowth($currentCogs, $prevCogs)],
                'gross_profit' => ['value' => $currentGrossProfit, 'growth' => $this->calculateGrowth($currentGrossProfit, $prevGrossProfit)],
                'gross_margin_percent' => ['value' => $currentGrossMargin, 'growth' => null],
                'total_items_sold' => ['value' => $currentTotalItems, 'growth' => null]
            ],
            'unit_economics' => [
                'gross_sales' => $currentGrossSales,
                'cogs' => $currentCogs,
                'gross_profit' => $currentGrossProfit,
                'gross_margin_percent' => $currentGrossMargin,
                'app_fee_income' => (float) $currentNet,
                'combined_income' => (float) $currentGrossProfit + (float) $currentNet,
                'total_items_sold' => $currentTotalItems
            ],
            'chart' => $chartData,
            'recent_orders' => $recentOrders
        ], 'Analytics data retrieved successfully');
    }

    public function exportExcel(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        
        // Gunakan package Excel (Menghasilkan format .xlsx rapi)
        return Excel::download(
            new TransactionsExport($startDate, $endDate), 
            "Laporan_Transaksi_{$startDate}_sd_{$endDate}.xlsx"
        );
    }

    /**
     * Export PDF (Membutuhkan barryvdh/laravel-dompdf)
     * Jika belum install: composer require barryvdh/laravel-dompdf
     */
    public function exportPdf(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        
        $orders = Order::with(['customer', 'outlet'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalGross = $orders->where('status', 'completed')->sum('total_price');
        
        // Gunakan DOMPDF untuk generate dari View Blade
        $pdf = Pdf::loadView('reports.transactions', compact('orders', 'startDate', 'endDate', 'totalGross'));
        
        return $pdf->download("Laporan_Transaksi_{$startDate}_sd_{$endDate}.pdf");
    }

    /**
     * Helper untuk hitung persentase kenaikan/penurunan
     */
    private function calculateGrowth($current, $prev)
    {
        if ($prev == 0) {
            return $current > 0 ? 100 : 0;
        }
        $growth = (($current - $prev) / $prev) * 100;
        return round($growth, 1); // Bulatkan 1 angka di belakang koma
    }



/**
     * GET PRODUCTS BY OUTLET
     * Mengambil produk spesifik untuk satu outlet via relasi pivot
     */
    public function getOutletProducts(Outlet $outlet)
    {
        // Ambil produk milik outlet ini lewat relasi many-to-many
        $products = $outlet->products()
            ->orderBy('category', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description,
                    'image_url' => $product->image_url,
                    'category' => $product->category,
                    'price' => $product->pivot->price ?? $product->price,
                    'is_available' => (bool) $product->pivot->is_available,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });

        return $this->successResponse($products, 'Products retrieved successfully');
    }


  
    /**
     * CREATE PRODUCT (Specific Outlet)
     * Hanya menambahkan produk ke satu outlet tertentu
     */
    public function createProduct(Request $request, Outlet $outlet)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category' => 'required|in:makanan,minuman',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $outlet, $validated) {
            // 1. Setup Data Master Produk
            $productData = [
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']) . '-' . Str::random(5),
                'price' => $validated['price'], // Harga Base
                'category' => $validated['category'],
                'description' => $request->description,
            ];

            // 2. Handle Image Upload
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('products', 'public');
                $productData['image_url'] = url('storage/' . $path);
            }

            // 3. Create Master Product (Tanpa outlet_id)
            $product = Product::create($productData);

            // 4. Attach ke Outlet spesifik (Pivot Table)
            $product->outlets()->attach($outlet->id, [
                'is_available' => true,
                'price' => $product->price // Harga cabang (bisa null jika mau ikut master)
            ]);

            return $this->successResponse($product, 'Product created and assigned to outlet successfully', 201);
        });
    }

    /**
     * CREATE GLOBAL PRODUCT (All Outlets)
     * Produk otomatis masuk ke SEMUA outlet yang ada
     */
   public function createGlobalProduct(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category' => 'required|in:makanan,minuman',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            // 1. Setup Data Master (Hanya data umum)
            $productData = [
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']) . '-' . Str::random(5),
                'price' => $validated['price'], // Harga Base
                'category' => $validated['category'],
                'description' => $request->description,
            ];

            // Handle Image
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('products', 'public');
                $productData['image_url'] = url('storage/' . $path);
            }

            // 2. Create Master Product (Tanpa outlet_id)
            $product = Product::create($productData);

            // 3. Distribusi ke SEMUA Outlet
            $outlets = Outlet::all();
            
            // Siapkan data pivot
            // Menggunakan syncWithoutDetaching atau attach dalam loop
            foreach($outlets as $outlet) {
                $product->outlets()->attach($outlet->id, [
                    'is_available' => true,      // Default ON
                    'price' => $product->price   // Harga cabang = Harga Master
                ]);
            }

            return $this->successResponse($product, 'Global product created and distributed to all outlets', 201);
        });
    }

    /**
     * UPDATE PRODUCT (Master Data)
     * Mengupdate data inti produk (Nama, Foto, Deskripsi)
     */
    public function updateProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0', // Update Base Price
            'category' => 'sometimes|in:makanan,minuman',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string',
        ]);

        // 1. Update Slug jika nama berubah
        if ($request->has('name')) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        }

        // 2. Handle Image Upload
        if ($request->hasFile('image')) {
            if ($product->getRawOriginal('image_url')) {
                // Hapus gambar lama
                $oldPath = $product->getRawOriginal('image_url');
                $oldPath = explode('storage/', $oldPath)[1] ?? $oldPath;
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = url('storage/' . $path);
        }

        // 3. Bersihkan field yang tidak ada di tabel products (jika frontend kirim is_available)
        unset($validated['is_available']);

        // 4. Update Master Product
        $product->update($validated);

        return $this->successResponse($product, 'Product updated successfully');
    }

    /**
     * DELETE PRODUCT
     * Menghapus produk dari Master dan otomatis hilang di semua outlet (Cascade)
     */
    public function deleteProduct(Product $product)
    {
        if ($product->getRawOriginal('image_url')) {
            $path = $product->getRawOriginal('image_url');
            $path = explode('storage/', $path)[1] ?? $path;
            Storage::disk('public')->delete($path);
        }
        
        // Cascade delete di database akan otomatis menghapus data di tabel pivot outlet_product
        $product->delete();

        return $this->successResponse(null, 'Product deleted successfully');
    }

    // ... method sebelumnya (createProduct, dll) ...

    /**
     * Get List of Drivers by Outlet
     */
    public function getOutletDrivers(Outlet $outlet)
    {
        // Ambil user dengan role 'driver' yang terikat di outlet ini
        $drivers = User::where('role', 'driver')
            ->where('outlet_id', $outlet->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($drivers, 'Drivers retrieved successfully');
    }

    /**
     * Create New Driver Account
     */

    public function getAllDrivers()
    {
        // Ambil SEMUA driver, sertakan info outlet dan hitung statistik
        $drivers = User::where('role', 'driver')
            ->with('outlet')
            ->withCount([
                'deliveries as completed_deliveries' => function ($query) {
                    $query->whereIn('status', ['delivered', 'completed']);
                }
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($driver) {
                // Cast wallet_balance ke float agar tidak null
                $driver->wallet_balance = (float) ($driver->wallet_balance ?? 0);
                // Tambahkan profile_image URL full
                if ($driver->profile_image) {
                    $driver->profile_image = url('storage/' . $driver->profile_image);
                }
                return $driver;
            });

        return $this->successResponse($drivers, 'All drivers retrieved successfully');
    }
    
    public function createDriver(Request $request, Outlet $outlet)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string', // Tambahan info driver (opsional)
            // Bisa tambahkan validasi plat nomor kendaraan dsb jika perlu
        ]);

        $driver = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => 'driver',
            'outlet_id' => $outlet->id,
            'is_online' => false, // Default offline
            'is_busy' => false,
        ]);

        return $this->successResponse($driver, 'Driver account created successfully', 201);
    }

    /**
     * Update Driver Account
     */
    public function updateDriver(Request $request, User $driver)
    {
        // Pastikan yang diedit benar-benar driver
        if ($driver->role !== 'driver') {
            return $this->errorResponse('User is not a driver', 400);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $driver->id,
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'vehicle_type' => 'nullable|string|in:motor,mobil',
            'plate_number' => 'nullable|string|max:20',
        ]);

        $data = [
            'name' => $validated['name'] ?? $driver->name,
            'email' => $validated['email'] ?? $driver->email,
            'phone' => $validated['phone'] ?? $driver->phone,
            'vehicle_type' => $validated['vehicle_type'] ?? $driver->vehicle_type,
            'plate_number' => $validated['plate_number'] ?? $driver->plate_number,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = bcrypt($validated['password']);
        }

        $driver->update($data);

        // Reload with outlet relation
        $driver->load('outlet');
        $driver->wallet_balance = (float) ($driver->wallet_balance ?? 0);
        if ($driver->profile_image) {
            $driver->profile_image = url('storage/' . $driver->profile_image);
        }

        return $this->successResponse($driver, 'Driver account updated successfully');
    }

    /**
     * Delete Driver Account
     */
    public function deleteDriver(User $driver)
    {
        if ($driver->role !== 'driver') {
            return $this->errorResponse('User is not a driver', 400);
        }

        // Cek apakah driver sedang punya order aktif? (Opsional)
        // $activeOrders = Order::where('driver_id', $driver->id)->whereIn('status', ['on_delivery'])->exists();
        // if ($activeOrders) return $this->errorResponse('Cannot delete driver with active orders', 400);

        $driver->delete();

        return $this->successResponse(null, 'Driver account deleted successfully');
    }

    /**
     * Get All Customers
     */
    public function getCustomers(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Ambil user role customer, urutkan dari yang terbaru
        // Kita juga hitung total order yang pernah dibuat (withCount)
        // Pastikan Model User punya relasi public function orders() { return $this->hasMany(Order::class); }
        $customers = User::where('role', 'customer')
            ->withCount('orders') 
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($customers, 'Customers retrieved successfully');
    }

    /**
     * Toggle Block/Unblock Customer
     */
    public function toggleBlockCustomer(User $user)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        if ($user->role !== 'customer') {
            return $this->errorResponse('User is not a customer', 400);
        }

        // Toggle status
        $newStatus = ($user->status === 'blocked') ? 'active' : 'blocked';
        $user->update(['status' => $newStatus]);

        $message = $newStatus === 'blocked'
            ? 'Pelanggan berhasil diblokir.'
            : 'Pelanggan berhasil diaktifkan kembali.';

        return $this->successResponse($user, $message);
    }

    /**
     * Delete Customer
     */
    public function deleteCustomer(User $user)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        if ($user->role !== 'customer') {
            return $this->errorResponse('User is not a customer', 400);
        }

        // Opsional: Cek order aktif
        // if ($user->orders()->whereIn('status', ['pending', 'process'])->exists()) { ... }

        $user->delete();

        return $this->successResponse(null, 'Customer deleted successfully');
    }

    /**
     * Get Main Dashboard Overview
     */
    public function getDashboardOverview(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Ubah jadi BULAN INI agar data selalu ada (tidak hanya hari ini)
        $startDate = \Carbon\Carbon::now()->startOfMonth();
        $endDate = \Carbon\Carbon::now()->endOfMonth();

        $prevStartDate = \Carbon\Carbon::now()->subMonth()->startOfMonth();
        $prevEndDate = \Carbon\Carbon::now()->subMonth()->endOfMonth();

        // ==============================================
        // CARD 1: TOTAL PENDAPATAN (Bulan Ini)
        // ==============================================
        $revenueCurrent = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('total_price');

        $revenuePrev = Order::whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->where('status', 'completed')
            ->sum('total_price');

        $revenueGrowth = $revenuePrev > 0 
            ? round((($revenueCurrent - $revenuePrev) / $revenuePrev) * 100, 1) 
            : ($revenueCurrent > 0 ? 100 : 0);

        // ==============================================
        // CARD 2: TOTAL ORDER (Bulan Ini)
        // ==============================================
        $ordersCurrent = Order::whereBetween('created_at', [$startDate, $endDate])->count();
        $newOrders = Order::where('status', 'pending')->count();

        // ==============================================
        // CARD 3: OUTLET AKTIF
        // ==============================================
        $totalOutlets = \App\Models\Outlet::count();
        $activeOutlets = \App\Models\Outlet::where('is_force_closed', false)->count();

        // ==============================================
        // CARD 4: PESANAN DITOLAK (REJECTED)
        // ==============================================
        $rejectedOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'cancelled')
            ->count();

        // ==============================================
        // TRAFFIC ORDER LIVE (Tetap Hari Ini)
        // ==============================================
        $today = \Carbon\Carbon::today();
        $trafficQuery = Order::selectRaw('HOUR(created_at) as hour, COUNT(*) as total')
            ->whereDate('created_at', $today)
            ->groupBy('hour')
            ->pluck('total', 'hour')
            ->toArray();

        $chartData = [];
        for ($i = 8; $i <= 22; $i++) {
            $hourStr = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
            $chartData[] = [
                'time' => $hourStr,
                'count' => $trafficQuery[$i] ?? 0
            ];
        }

        // ==============================================
        // AKTIVITAS TERBARU (Fix Deskripsi Kosong)
        // ==============================================
        $recentActivities = Order::with('outlet')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                $time = $order->updated_at->format('H:i');
                $outletName = $order->outlet ? $order->outlet->name : 'Unknown Outlet';
                
                // Fallback / Default text jika status tidak match
                $title = 'Aktivitas Sistem';
                $desc = "Terjadi pembaruan pada pesanan #{$order->id} ({$order->status}).";
                $type = 'system';

                switch ($order->status) {
                    case 'completed':
                        $title = 'Order Selesai';
                        $desc = "{$outletName} menyelesaikan pesanan #{$order->id}.";
                        $type = 'success';
                        break;
                    case 'pending':
                        $title = 'Pesanan Baru Masuk';
                        $desc = "Pesanan senilai Rp" . number_format($order->total_price, 0, ',', '.') . " masuk di {$outletName}.";
                        $type = 'info';
                        break;
                    case 'cancelled':
                        $title = 'Order Dibatalkan';
                        $desc = "Pesanan #{$order->id} ditolak/dibatalkan oleh {$outletName}.";
                        $type = 'warning';
                        break;
                    case 'refund_needed':
                        $title = 'Request Refund';
                        $desc = "Pesanan #{$order->id} ditolak dan butuh proses refund.";
                        $type = 'danger';
                        break;
                    case 'refunded':
                        $title = 'Refund Selesai';
                        $desc = "Refund pesanan #{$order->id} berhasil diproses.";
                        $type = 'success';
                        break;
                }

                return [
                    'id' => $order->id,
                    'time' => $time,
                    'title' => $title,
                    'description' => $desc,
                    'type' => $type
                ];
            });

        return $this->successResponse([
            'cards' => [
                'revenue' => [
                    'value' => $revenueCurrent,
                    'growth' => $revenueGrowth,
                    'label' => $revenueGrowth >= 0 ? "+{$revenueGrowth}% dari bulan lalu" : "{$revenueGrowth}% dari bulan lalu"
                ],
                'orders' => [
                    'value' => $ordersCurrent,
                    'label' => "+{$newOrders} order baru (pending)"
                ],
                'outlets' => [
                    'active' => $activeOutlets,
                    'total' => $totalOutlets,
                    'label' => $activeOutlets === $totalOutlets ? "Semua outlet buka" : "{$activeOutlets} dari {$totalOutlets} aktif"
                ],
                'rejected' => [
                    'count' => $rejectedOrders,
                    'label' => $rejectedOrders > 0 ? "Bulan ini" : "Tidak ada penolakan"
                ]
            ],
            'chart' => $chartData,
            'recent_activities' => $recentActivities
        ], 'Dashboard overview retrieved successfully');
    }           
    
}
