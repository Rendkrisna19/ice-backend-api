<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Mail\RegisterOtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register new user
     */
    public function register(Request $request)
    {
        return $this->errorResponse('Gunakan endpoint verifikasi OTP untuk mendaftar.', 422);
    }

    /**
     * Request OTP for registration
     */
    public function requestRegisterOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        $email = Str::lower($validated['email']);

        if (User::where('email', $email)->exists()) {
            return $this->errorResponse('Email sudah terdaftar.', 409);
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);

        EmailOtp::updateOrCreate(
            ['email' => $email],
            [
                'code_hash' => Hash::make($otp),
                'expires_at' => $expiresAt,
            ]
        );

        Mail::to($email)->send(new RegisterOtpMail($otp));

        return $this->successResponse([
            'email' => $email,
            'expires_at' => $expiresAt,
        ], 'OTP terkirim ke email.', 200);
    }

    /**
     * Verify OTP and register user
     */
    public function verifyRegisterOtp(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|in:customer,admin,cashier,driver',
            'phone' => 'nullable|string',
            'otp' => 'required|string|size:6',
        ]);

        $email = Str::lower($validated['email']);

        if (User::where('email', $email)->exists()) {
            return $this->errorResponse('Email sudah terdaftar.', 409);
        }

        $otpRecord = EmailOtp::where('email', $email)->first();

        if (!$otpRecord) {
            return $this->errorResponse('OTP belum diminta atau sudah kedaluwarsa.', 422);
        }

        if ($otpRecord->expires_at->isPast()) {
            $otpRecord->delete();

            return $this->errorResponse('OTP sudah kedaluwarsa.', 422);
        }

        if (!Hash::check($validated['otp'], $otpRecord->code_hash)) {
            return $this->errorResponse('OTP tidak valid.', 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'customer',
            'phone' => $validated['phone'] ?? null,
            'email_verified_at' => now(),
        ]);

        $otpRecord->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
        ], 'User registered successfully', 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Email atau password Anda salah', 401);
        }

        // Cek apakah akun diblokir
        if ($user->status === 'blocked') {
            return $this->errorResponse('Akun Anda telah diblokir. Silakan hubungi admin untuk informasi lebih lanjut.', 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Get authenticated user
     */
    public function getUser(Request $request)
    {
        return $this->successResponse(auth()->user(), 'User retrieved successfully');
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        auth()->user()->tokens()->delete();

        return $this->successResponse(null, 'Logout successful');
    }

    /**
     * Delete authenticated customer account permanently
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'customer') {
            return $this->errorResponse('Hanya akun customer yang dapat dihapus melalui endpoint ini.', 403);
        }

        // Hapus semua token aktif (logout dari semua device)
        $user->tokens()->delete();

        // Hapus akun permanen
        $user->delete();

        return $this->successResponse(null, 'Akun berhasil dihapus.');
    }

    /**
     * Request OTP for Password Reset (Khusus Customer)
     */
    public function requestPasswordResetOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        $email = Str::lower($validated['email']);
        $user = User::where('email', $email)->first();

        if (!$user) {
            return $this->errorResponse('Email tidak terdaftar.', 404);
        }

        if ($user->role !== 'customer') {
            return $this->errorResponse('Reset password hanya tersedia untuk akun customer.', 403);
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);

        EmailOtp::updateOrCreate(
            ['email' => $email],
            [
                'code_hash' => Hash::make($otp),
                'expires_at' => $expiresAt,
            ]
        );

        Mail::to($email)->send(new \App\Mail\PasswordResetOtpMail($otp));

        return $this->successResponse([
            'email' => $email,
            'expires_at' => $expiresAt,
        ], 'OTP terkirim ke email Anda.', 200);
    }

    /**
     * Verify OTP and Reset Password (Khusus Customer)
     */
    public function verifyPasswordResetOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = Str::lower($validated['email']);
        $user = User::where('email', $email)->first();

        if (!$user) {
            return $this->errorResponse('Email tidak ditemukan.', 404);
        }

        $otpRecord = EmailOtp::where('email', $email)->first();

        if (!$otpRecord) {
            return $this->errorResponse('OTP belum diminta atau sudah digunakan.', 422);
        }

        if ($otpRecord->expires_at->isPast()) {
            $otpRecord->delete();
            return $this->errorResponse('OTP sudah kedaluwarsa.', 422);
        }

        if (!Hash::check($validated['otp'], $otpRecord->code_hash)) {
            return $this->errorResponse('OTP tidak valid.', 422);
        }

        if (Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Password baru tidak boleh sama dengan password sebelumnya.', 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $otpRecord->delete();

        return $this->successResponse(null, 'Password berhasil diubah. Silakan login kembali.', 200);
    }
}
