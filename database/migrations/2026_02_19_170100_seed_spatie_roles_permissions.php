<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var array<string>
     */
    private array $permissions = [
        'view dashboard',
        'manage dashboard',
        'view accounts',
        'manage accounts',
        'view transactions',
        'manage transactions',
        'view portfolio',
        'manage portfolio',
        'view suggestions',
        'manage suggestions',
        'view docs',
        'manage docs',
        'view settings',
        'manage settings',
        'view users',
        'manage users',
        'view notifications',
        'manage notifications',
        'view integrations',
        'manage integrations',
        'view ml',
        'manage ml',
        'view exports',
        'manage exports',
    ];

    public function up(): void
    {
        if (!$this->permissionTablesReady()) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $roleMap = [
            'super-admin' => $this->permissions,
            'admin' => $this->permissions,
            'manager' => [
                'view dashboard',
                'manage dashboard',
                'view accounts',
                'manage accounts',
                'view transactions',
                'manage transactions',
                'view portfolio',
                'manage portfolio',
                'view suggestions',
                'manage suggestions',
                'view docs',
                'view settings',
                'view users',
                'manage users',
                'view notifications',
                'manage notifications',
                'view integrations',
                'manage integrations',
                'view ml',
                'manage ml',
                'view exports',
                'manage exports',
            ],
            'user' => [
                'view dashboard',
                'view accounts',
                'manage accounts',
                'view transactions',
                'manage transactions',
                'view portfolio',
                'manage portfolio',
                'view suggestions',
                'view notifications',
            ],
            'test-role' => [
                'view dashboard',
            ],
        ];

        foreach ($roleMap as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        if (Schema::hasTable('users')) {
            $users = User::query()->get();

            foreach ($users as $user) {
                $legacyRole = $user->role ?: 'user';
                $targetRole = array_key_exists($legacyRole, $roleMap) ? $legacyRole : 'user';
                $user->syncRoles([$targetRole]);

                $legacyPermissions = [];
                if (Schema::hasColumn('users', 'permissions')) {
                    $raw = $user->getRawOriginal('permissions');
                    $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                    $legacyPermissions = collect(Arr::wrap($decoded))
                        ->map(fn ($permission) => trim((string) $permission))
                        ->filter(fn ($permission) => $permission !== '' && Permission::where('name', $permission)->exists())
                        ->values()
                        ->all();
                }

                if (!empty($legacyPermissions)) {
                    $user->syncPermissions($legacyPermissions);
                }
            }

            if (!User::role('super-admin')->exists() && !User::role('admin')->exists()) {
                $firstUser = User::query()->oldest('id')->first();
                if ($firstUser) {
                    $firstUser->syncRoles(['super-admin']);
                    $firstUser->forceFill(['role' => 'super-admin'])->save();
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!$this->permissionTablesReady()) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::whereIn('name', ['super-admin', 'admin', 'manager', 'user', 'test-role'])->get()->each(function (Role $role) {
            $role->syncPermissions([]);
            $role->delete();
        });

        Permission::whereIn('name', $this->permissions)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionTablesReady(): bool
    {
        $tables = config('permission.table_names', []);

        if (!is_array($tables) || empty($tables)) {
            return false;
        }

        return Schema::hasTable($tables['permissions'])
            && Schema::hasTable($tables['roles'])
            && Schema::hasTable($tables['model_has_permissions'])
            && Schema::hasTable($tables['model_has_roles'])
            && Schema::hasTable($tables['role_has_permissions']);
    }
};
