<?php

namespace App\Http\Middleware;

use App\Models\Outlet;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        // Check if outlet is force closed
        if ($outlet->is_force_closed) {
            return response()->json([
                'success' => false,
                'message' => 'Maaf, Toko Sedang Tutup.',
            ], 403);
        }

        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');
        $openingHour = $outlet->opening_hour->format('H:i:s');
        $closingHour = $outlet->closing_hour->format('H:i:s');

        if ($currentTime < $openingHour || $currentTime > $closingHour) {
            return response()->json([
                'success' => false,
                'message' => 'Maaf, Toko Sedang Tutup.',
            ], 403);
        }

        return $next($request);
    }
}
