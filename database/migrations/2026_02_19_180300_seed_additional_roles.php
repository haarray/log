<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->permissionTablesReady()) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = Permission::query()->orderBy('name')->pluck('name')->all();
        $testRolePermissions = ['view dashboard', 'view docs', 'view settings'];

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->syncPermissions($allPermissions);

        $testRole = Role::findOrCreate('test-role', 'web');
        $testRole->syncPermissions(array_values(array_intersect($testRolePermissions, $allPermissions)));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!$this->permissionTablesReady()) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->whereIn('name', ['super-admin', 'test-role'])->get()->each(function (Role $role): void {
            $role->syncPermissions([]);
            $role->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionTablesReady(): bool
    {
        $tables = config('permission.table_names', []);
        if (!is_array($tables) || empty($tables)) {
            return false;
        }

        return !empty($tables['permissions'])
            && !empty($tables['roles'])
            && Schema::hasTable($tables['permissions'])
            && Schema::hasTable($tables['roles'])
            && Schema::hasTable($tables['model_has_roles'])
            && Schema::hasTable($tables['role_has_permissions']);
    }
};
