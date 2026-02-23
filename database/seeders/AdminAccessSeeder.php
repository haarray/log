<?php

namespace Database\Seeders;

use App\Support\RbacBootstrap;
use Illuminate\Database\Seeder;

class AdminAccessSeeder extends Seeder
{
    public function run(): void
    {
        /** @var RbacBootstrap $rbac */
        $rbac = app(RbacBootstrap::class);
        $rbac->syncPermissionsAndRoles();

        $defaultPassword = (string) env('HAARRAY_ADMIN_PASSWORD', 'Admin@12345');
        $seedUsers = [
            [
                'name' => 'Prateek Bhujel',
                'email' => 'prateekbhujelpb@gmail.com',
                'password' => $defaultPassword,
                'role' => 'super-admin',
            ],
            [
                'name' => 'System Admin',
                'email' => 'admin@admin.com',
                'password' => $defaultPassword,
                'role' => 'admin',
            ],
        ];

        $rbac->ensureUsers($seedUsers);
    }
}

