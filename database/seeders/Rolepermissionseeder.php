<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission slugs are grouped by module.
     * Format: <module>.<action>
     */
    private array $permissions = [
        // User management
        'user.view', 'user.create', 'user.update', 'user.delete', 'user.assign-role',

        // Company
        'company.view', 'company.create', 'company.update', 'company.delete',

        // Form template
        'template.view', 'template.create', 'template.update',
        'template.delete', 'template.publish',

        // Inspection
        'inspection.view-all', 'inspection.view-own', 'inspection.create',
        'inspection.update-own', 'inspection.delete', 'inspection.submit',
        'inspection.upload',

        // Approval
        'approval.view', 'approval.approve', 'approval.reject', 'approval.revise',

        // Report
        'report.view', 'report.export-pdf', 'report.export-excel',

        // Audit
        'audit.view',

        // Dashboard
        'dashboard.view-stats',
    ];

    /** Permissions granted per role */
    private array $rolePermissions = [
        'super_admin' => '*', // all permissions

        'admin' => [
            'user.view', 'user.create', 'user.update', 'user.assign-role',
            'company.view',
            'template.view', 'template.create', 'template.update', 'template.publish',
            'inspection.view-all', 'inspection.create', 'inspection.delete', 'inspection.upload',
            'approval.view', 'approval.approve', 'approval.reject',
            'report.view', 'report.export-pdf', 'report.export-excel',
            'audit.view', 'dashboard.view-stats',
        ],

        'inspector' => [
            'template.view',
            'inspection.view-own', 'inspection.update-own',
            'inspection.submit', 'inspection.upload',
            'report.view', 'report.export-pdf',
        ],

        'reviewer' => [
            'template.view',
            'inspection.view-all',
            'approval.view', 'approval.approve', 'approval.reject', 'approval.revise',
            'report.view', 'report.export-pdf', 'report.export-excel',
            'dashboard.view-stats',
        ],

        'viewer' => [
            'template.view',
            'inspection.view-all',
            'approval.view',
            'report.view',
        ],
    ];

    private array $roles = [
        ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Akses penuh ke seluruh sistem.'],
        ['name' => 'Admin',       'slug' => 'admin',       'description' => 'Kelola user, template, dan laporan.'],
        ['name' => 'Inspector',   'slug' => 'inspector',   'description' => 'Isi dan submit form inspeksi.'],
        ['name' => 'Reviewer',    'slug' => 'reviewer',    'description' => 'Review dan approve hasil inspeksi.'],
        ['name' => 'Viewer',      'slug' => 'viewer',      'description' => 'Akses read-only ke laporan.'],
    ];

    public function run(): void
    {
        // Create permissions
        $permissionModels = [];
        foreach ($this->permissions as $slug) {
            $module = explode('.', $slug)[0];
            $permissionModels[$slug] = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst(str_replace(['.', '-'], [' ', ' '], $slug)), 'module' => $module]
            );
        }

        // Create roles and attach permissions
        foreach ($this->roles as $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                ['name' => $roleData['name'], 'description' => $roleData['description']]
            );

            $allowed = $this->rolePermissions[$roleData['slug']];

            if ($allowed === '*') {
                $role->permissions()->sync(array_values(
                    array_map(fn ($p) => $p->id, $permissionModels)
                ));
            } else {
                $ids = array_map(fn ($slug) => $permissionModels[$slug]->id, $allowed);
                $role->permissions()->sync($ids);
            }
        }
    }
}