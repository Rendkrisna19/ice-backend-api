<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Product;
use App\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    /**
     * Get all products
     */
    public function index(Request $request)
    {
        $products = Product::paginate(20);

        return $this->successResponse($products, 'Products retrieved successfully');
    }

    /**
     * Get single product
     */
    public function show(Product $product)
    {
        return $this->successResponse($product, 'Product retrieved successfully');
    }

    /**
     * Search products
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (strlen($query) < 2) {
            return $this->errorResponse('Search query must be at least 2 characters', 400);
        }

        $products = Product::where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->paginate(20);

        return $this->successResponse($products, 'Products searched successfully');
    }

    /**
     * Get products by category
     */
    public function byCategory(Request $request)
    {
        $category = $request->input('category');

        if (!$category) {
            return $this->errorResponse('Category is required', 400);
        }

        $products = Product::where('category', $category)->paginate(20);

        return $this->successResponse($products, 'Products retrieved successfully');
    }

    
}
