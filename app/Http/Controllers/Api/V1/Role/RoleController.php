<?php

namespace App\Http\Controllers\Api\V1\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Role;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Services\AuditService;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::all();

        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:255', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
        ]);

        $role = Role::create($data);
        if (isset($data['permissions'])) {
            $role->permissions()->sync($data['permissions']);
        }

        AuditService::log('role.created', 'role', $role->id, Role::class);

        return response()->json([
            'message' => 'Role created successfully',
            'role'    => $role,
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:255', 'unique:roles,slug,' . $role->id],
            'description' => ['nullable', 'string'],
        ]);

        $role->update($data);
        if (isset($data['permissions'])) {
            $role->permissions()->sync($data['permissions']);
        }

        AuditService::log('role.updated', 'role', $role->id, Role::class);

        return response()->json([
            'message' => 'Role updated successfully',
            'role'    => $role,
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        AuditService::log('role.deleted', 'role', $role->id, Role::class);

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'role' => $role->load('permissions'),
        ]); 
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::all();
        return response()->json([
            'permissions' => $permissions,
        ]);
    }

    public function permissionsUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'permission_id' => ['nullable', 'array'], 
            'permission_id.*' => ['exists:permissions,id'],
        ]);

        $role = Role::findOrFail($data['role_id']);

        $permissionIds = $data['permission_id'] ?? [];
        $role->permissions()->sync($permissionIds);

        AuditService::log('role.permissions_updated', 'role', $role->id, Role::class);

        return response()->json([
            'message' => 'Role permissions updated successfully',
            'role'    => $role->load('permissions'),
        ]);
    }
}
