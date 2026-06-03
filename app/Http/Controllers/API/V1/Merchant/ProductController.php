<?php

namespace App\Http\Controllers\API\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use ApiResponse;

    /**
     * Helper untuk memformat URL gambar dengan benar
     */
    private function formatImageUrl($path)
    {
        if (!$path) return null;
        // Jika sudah berbentuk URL lengkap (http/https), biarkan saja
        if (str_starts_with($path, 'http')) return $path;
        // Jika belum, tambahkan base url aplikasi + /storage/
        return url('storage/' . $path);
    }

    /**
     * Get All Products for Current Outlet
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->outlet_id) {
            return $this->errorResponse('User tidak memiliki outlet.', 403);
        }

        $products = Product::where('outlet_id', $user->outlet_id)
            ->orderBy('category', 'asc')
            ->get()
            ->map(function ($product) {
                return [
                    'id'           => $product->id,
                    'name'         => $product->name,
                    'image_url'    => (!empty($product->image_url) && !str_starts_with($product->image_url, 'http')) ? $product->image_url : $product->image_url,
                    'category'     => $product->category,
                    'price'        => $product->price,
                    'cost_price'   => $product->cost_price,
                    'is_available' => (bool) $product->is_available, 
                ];
            });

        return $this->successResponse($products, 'Menu items retrieved');
    }

    /**
     * Create New Product
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user->outlet_id) {
            return $this->errorResponse('User tidak terikat dengan outlet manapun', 403);
        }

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'price'        => 'required|numeric|min:0',
            'cost_price'   => 'nullable|numeric|min:0',
            'category'     => 'required|in:makanan,minuman,food,beverage', 
            'image'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description'  => 'nullable|string',
            'is_available' => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $user, $validated) {
            $kategori = in_array($validated['category'], ['food', 'makanan']) ? 'makanan' : 'minuman';

            $productData = [
                'outlet_id'    => $user->outlet_id,
                'name'         => $validated['name'],
                'slug'         => Str::slug($validated['name']) . '-' . Str::random(5),
                'price'        => $validated['price'],
                'cost_price'   => $validated['cost_price'] ?? null,
                'category'     => $kategori,
                'description'  => $request->description,
                'is_available' => $request->boolean('is_available', true),
            ];

            // Handle Upload Gambar saat buat baru
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('products', 'public');
                $productData['image_url'] = 'storage/' . $path;
            }

            $product = Product::create($productData);

            // Tambahkan ke tabel outlet_product
            $product->outlets()->attach($user->outlet_id, [
                'price' => $product->price,
                'is_available' => $product->is_available,
            ]);

            $product->image_url = $this->formatImageUrl($product->image_url);
            return $this->successResponse($product, 'Menu berhasil ditambahkan', 201);
        });
    }

    /**
     * Update Product
     * CATATAN: Karena FormData + File, Laravel sering butuh method POST dengan flag _method=PUT
     * Tapi logika tetap sama.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        // 1. Pastikan produk ini milik outlet si kasir
        $product = Product::where('outlet_id', $user->outlet_id)->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found or access denied', 404);
        }

        // Konversi boolean
        if ($request->has('is_available')) {
            $request->merge([
                'is_available' => filter_var($request->input('is_available'), FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'price'        => 'sometimes|numeric|min:0',
            'cost_price'   => 'sometimes|numeric|min:0',
            'category'     => 'sometimes|in:makanan,minuman,food,beverage',
            'image'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description'  => 'nullable|string',
            'is_available' => 'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($request, $product, $validated, $user) {
            if (isset($validated['name'])) {
                $product->name = $validated['name'];
                $product->slug = Str::slug($validated['name']) . '-' . Str::random(5);
            }
            if (isset($validated['category'])) {
                $product->category = in_array($validated['category'], ['food', 'makanan']) ? 'makanan' : 'minuman';
            }
            if (isset($validated['price'])) $product->price = $validated['price'];
            if (isset($validated['cost_price'])) $product->cost_price = $validated['cost_price'];
            if (isset($request->description)) $product->description = $request->description;
            if (isset($validated['is_available'])) $product->is_available = $validated['is_available'];

            // Handle Image Replacement saat Update
            if ($request->hasFile('image')) {
                if ($product->getRawOriginal('image_url')) {
                    $oldPath = $product->getRawOriginal('image_url');
                    $oldPath = explode('storage/', $oldPath)[1] ?? $oldPath;
                    Storage::disk('public')->delete($oldPath);
                }
                $path = $request->file('image')->store('products', 'public');
                $product->image_url = 'storage/' . $path;
            }

            $product->save();

            // Update juga outlet_product
            $product->outlets()->updateExistingPivot($user->outlet_id, [
                'price' => $product->price,
                'is_available' => $product->is_available,
            ]);

            $product->image_url = $this->formatImageUrl($product->image_url);
            return $this->successResponse($product, 'Menu item updated successfully');
        });
    }

    /**
     * Delete Product
     */
    public function destroy($id)
    {
        $user = Auth::user();

        $product = Product::where('outlet_id', $user->outlet_id)->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found or access denied', 404);
        }

        $user = auth()->user();
        return DB::transaction(function () use ($product, $user) {
            // Hapus file gambar dari server
            if ($product->getRawOriginal('image_url')) {
                $path = $product->getRawOriginal('image_url');
                $path = explode('storage/', $path)[1] ?? $path;
                Storage::disk('public')->delete($path);
            }

            // Hapus relasi di outlet_product
            $product->outlets()->detach($user->outlet_id);

            $product->delete();

            return $this->successResponse(null, 'Menu item deleted successfully');
        });
    }

    /**
     * Quick Toggle Availability (ON/OFF)
     */
    public function toggleStatus(Request $request, $id)
    {
        $user = Auth::user();

        $product = Product::where('outlet_id', $user->outlet_id)->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found in this outlet', 404);
        }

        $product->is_available = !$product->is_available;
        $product->save();

        return $this->successResponse(
            ['is_available' => $product->is_available], 
            'Product status updated'
        );
    }
}