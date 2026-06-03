<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Outlet;
use App\Models\Product;
use App\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OutletController extends Controller
{
    use ApiResponse;

    /**
     * Get all outlets
     */
    public function index(Request $request)
    {
        $outlets = Outlet::where('is_force_closed', false)
            ->paginate(20);

        return $this->successResponse($outlets, 'Outlets retrieved successfully');
    }

    /**
     * Get single outlet details (with logo & banner)
     */
    public function show($id)
    {
        // LOGIC BARU: Ambil produk HANYA jika is_available = true
        $outlet = Outlet::with(['products' => function($query) {
            $query->where('outlet_product.is_available', true);
        }, 'users'])->findOrFail($id);

        return $this->successResponse($outlet, 'Outlet detail fetched');
    }

    /**
     * Get outlet products
     */
    public function getProducts(Outlet $outlet)
    {
        // LOGIC BARU: Hapus wherePivot, ganti where biasa
        $products = $outlet->products()
            ->where('outlet_product.is_available', true)
            ->paginate(20);

        return $this->successResponse($products, 'Outlet products retrieved successfully');
    }

    /**
     * Search outlets
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (strlen($query) < 2) {
            return $this->errorResponse('Search query must be at least 2 characters', 400);
        }

        $outlets = Outlet::where('name', 'like', "%{$query}%")
            ->orWhere('address', 'like', "%{$query}%")
            ->where('is_force_closed', false)
            ->paginate(20);

        return $this->successResponse($outlets, 'Outlets searched successfully');
    }

    /**
     * Upload logo/banner outlet
     */
    public function uploadMedia(Request $request, $outletId)
    {
        $request->validate([
            'type' => 'required|in:logo,banner',
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $outlet = Outlet::findOrFail($outletId);
        $type = $request->input('type');

        // Hapus file lama jika ada
        if ($outlet->$type && file_exists(public_path($outlet->$type))) {
            @unlink(public_path($outlet->$type));
        }

        $file = $request->file('image');
        $filename = 'file/outlet/' . $type . '_' . $outlet->id . '_' . Str::uuid() . '.' . $file->  ClientOriginalExtension();
        $file->move(public_path('file/outlet'), basename($filename));

        $outlet->$type = $filename;
        $outlet->save();

        return $this->successResponse([
            $type => $filename,
            'outlet' => $outlet,
        ], ucfirst($type) . ' outlet berhasil diupdate');
    }
}
