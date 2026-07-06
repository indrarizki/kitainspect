<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Role & permissions
            RolePermissionSeeder::class,

            // 2. AQL lookup table (immutable reference data)
            AqlTableSeeder::class,

            // 3. Inspection types
            InspectionTypeSeeder::class,

            // 4. Default company + super admin user
            SuperAdminSeeder::class,
        ]);
    }
}