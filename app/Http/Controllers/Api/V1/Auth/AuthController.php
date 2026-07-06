<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     *
     * Authenticate user and return a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)
            ->with(['role.permissions', 'company'])
            ->first();

        // Check user exists, password valid, and account active
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.',
            ], 403);
        }

        // Revoke previous tokens (single-session enforcement)
        $user->tokens()->delete();

        $token = $user->createToken(
            name: 'kitainspect-web',
            abilities: $this->resolveAbilities($user),
            expiresAt: now()->addDays(7),
        );

        return response()->json([
            'message' => 'Login berhasil.',
            'token'   => $token->plainTextToken,
            'user'    => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * GET /api/v1/auth/me
     *
     * Return authenticated user's profile with role & permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role.permissions', 'company']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * PUT /api/v1/auth/password
     *
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Password lama wajib diisi.',
            'password.required'         => 'Password baru wajib diisi.',
            'password.min'              => 'Password baru minimal 8 karakter.',
            'password.confirmed'        => 'Konfirmasi password tidak cocok.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password lama tidak sesuai.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all tokens — force re-login on all devices
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan login kembali.',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Build Sanctum token abilities from user's permission slugs.
     * This lets middleware do a quick token-level check before hitting the DB.
     */
    private function resolveAbilities(User $user): array
    {
        if (! $user->role) {
            return [];
        }

        return $user->role->permissions->pluck('slug')->toArray();
    }
}