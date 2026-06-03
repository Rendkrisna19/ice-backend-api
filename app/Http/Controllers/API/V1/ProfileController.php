<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    use ApiResponse;

    /**
     * Update or upload user profile image
     */
    public function updatePhoto(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'profile_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Hapus file lama jika ada
        if ($user->profile_image) {
            Storage::disk('public')->delete(str_replace('storage/', '', $user->profile_image));
        }

        $path = $request->file('profile_image')->store('profile-photos', 'public');
        $user->profile_image = 'storage/' . $path;
        $user->save();

        return $this->successResponse([
            'profile_image' => 'storage/' . $path,
            'user' => $user,
        ], 'Foto profil berhasil diupdate');
    }

    /**
     * Update user profile data (name, email, phone, password)
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string',
            'password' => 'sometimes|nullable|string|min:8|confirmed',
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return $this->successResponse($user, 'Profil berhasil diupdate');
    }
}
