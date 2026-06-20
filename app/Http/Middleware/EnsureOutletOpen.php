<?php

namespace App\Http\Middleware;

use App\Models\Outlet;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOutletOpen
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $outletId = $request->input('outlet_id') ?? $request->route('outlet_id');

        if (!$outletId) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet ID is required',
            ], 400);
        }

        $outlet = Outlet::find($outletId);

        if (!$outlet) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet not found',
            ], 404);
        }

        if (!$outlet->isOpenNow()) {
            return response()->json([
                'success' => false,
                'message' => 'Maaf, Toko Sedang Tutup.',
            ], 403);
        }

        return $next($request);
    }
}
