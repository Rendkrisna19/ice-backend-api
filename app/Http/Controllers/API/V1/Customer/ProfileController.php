<?php

namespace App\Http\Controllers\API\V1\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // 1. GET PROFILE
    public function show(Request $request)
    {
        $user = $request->user();
        
        // Cek path foto, jika ada convert ke Full URL
        $photoUrl = null;
        if ($user->profile_photo_path) {
            $photoUrl = asset('storage/' . $user->profile_photo_path);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number, // Pastikan kolom ini ada di DB
                'profile_photo_url' => $photoUrl,
            ]
        ], 200);
    }

    // 2. UPDATE PROFILE
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone_number' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Profil disimpan', 'data' => $user], 200);
    }

    // 3. UPDATE PHOTO
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();

        if ($request->hasFile('photo')) {
            // Hapus foto lama
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            // Upload baru
            $path = $request->file('photo')->store('profile-photos', 'public');
            $user->update(['profile_photo_path' => $path]);

            return response()->json([
                'status' => 'success',
                'data' => ['profile_photo_url' => asset('storage/' . $path)]
            ], 200);
        }

        return response()->json(['message' => 'Gagal upload'], 400);
    }
}