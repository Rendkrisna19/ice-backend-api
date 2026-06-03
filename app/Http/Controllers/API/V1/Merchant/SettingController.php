<?php

namespace App\Http\Controllers\API\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ApiResponse;

    public function getOperationalHours()
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $outlet = Outlet::find($user->outlet_id);

        if (!$outlet) {
            return $this->errorResponse('Outlet tidak ditemukan', 404);
        }

        return $this->successResponse([
            'opening_hour' => $outlet->opening_hour ? date('H:i', strtotime($outlet->opening_hour)) : null,
            'closing_hour' => $outlet->closing_hour ? date('H:i', strtotime($outlet->closing_hour)) : null,
        ], 'Jam operasional berhasil diambil');
    }

    public function updateOperationalHours(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'cashier' || !$user->outlet_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'opening_hour' => 'required|date_format:H:i',
            'closing_hour' => 'required|date_format:H:i',
        ]);

        $outlet = Outlet::find($user->outlet_id);

        if (!$outlet) {
            return $this->errorResponse('Outlet tidak ditemukan', 404);
        }

        $outlet->update([
            'opening_hour' => $validated['opening_hour'],
            'closing_hour' => $validated['closing_hour'],
        ]);

        return $this->successResponse([
            'opening_hour' => date('H:i', strtotime($outlet->opening_hour)),
            'closing_hour' => date('H:i', strtotime($outlet->closing_hour)),
        ], 'Jam operasional berhasil diperbarui');
    }
}