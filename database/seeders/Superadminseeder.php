<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['code' => 'KITAINSPECT'],
            [
                'name'      => 'kitaINSPECT HQ',
                'is_active' => true,
            ]
        );

        $role = Role::where('slug', 'super_admin')->firstOrFail();

        User::firstOrCreate(
            ['email' => 'superadmin@kitainspect.id'],
            [
                'name'       => 'Super Admin',
                'password'   => Hash::make('password'),
                'role_id'    => $role->id,
                'company_id' => $company->id,
                'is_active'  => true,
            ]
        );

        $this->command->info('Super admin created: superadmin@kitainspect.id / password');
    }
}