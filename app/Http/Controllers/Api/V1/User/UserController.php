<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = User::with(['role', 'company'])->latest();

        // Admin hanya lihat user di company-nya sendiri
        if (! $user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        if ($request->filled('role')) {
            $query->whereHas('role', fn ($q) => $q->where('slug', $request->role));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('email', 'ilike', "%{$request->search}%");
            });
        }

        return response()->json(
            UserResource::collection(
                $query->paginate($request->integer('per_page', 15))
            )
        );
    }

    /**
     * POST /api/v1/users
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8'],
            'role_id'    => ['required', 'uuid', 'exists:roles,id'],
            'company_id' => ['nullable', 'uuid', 'exists:companies,id'],
        ]);

        $user = User::create([
            ...$data,
            'password'   => Hash::make($data['password']),
            'company_id' => $data['company_id'] ?? $authUser->company_id,
            'is_active'  => true,
        ]);

        AuditService::log('user.created', 'user', $user->id, User::class);

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'user'    => new UserResource($user->load(['role', 'company'])),
        ], 201);
    }

    /**
     * GET /api/v1/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user->load(['role', 'company'])),
        ]);
    }

    /**
     * PUT /api/v1/users/{user}
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'email'     => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);
        AuditService::log('user.updated', 'user', $user->id, User::class);

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'user'    => new UserResource($user->load(['role', 'company'])),
        ]);
    }

    /**
     * DELETE /api/v1/users/{user} — soft deactivate
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak dapat menonaktifkan akun sendiri.'], 422);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete(); // force logout
        AuditService::log('user.deactivated', 'user', $user->id, User::class);

        return response()->json(['message' => 'User berhasil dinonaktifkan.']);
    }

    /**
     * PUT /api/v1/users/{user}/role
     */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
        ]);

        $oldRole = $user->role?->slug;
        $user->update(['role_id' => $data['role_id']]);

        AuditService::log('user.role_assigned', 'user', $user->id, User::class, [
            'old_role' => $oldRole,
            'new_role' => Role::find($data['role_id'])?->slug,
        ]);

        return response()->json([
            'message' => 'Role berhasil diubah.',
            'user'    => new UserResource($user->load(['role', 'company'])),
        ]);
    }

    /**
     * GET /api/v1/roles — list all roles (for dropdown)
     */
    public function roles(): JsonResponse
    {
        return response()->json([
            'roles' => Role::select('id', 'name', 'slug')->get(),
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {

        $password = 'kitainspect';

        $user->update(['password' => Hash::make($password)]);
        AuditService::log('user.password_reset', 'user', $user->id, User::class);

        return response()->json([
            'message' => 'Password berhasil direset.',
        ]);
    }
}