<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RbacBootstrap
{
    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [
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
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function roleMap(): array
    {
        $all = $this->permissions();

        return [
            'super-admin' => $all,
            'admin' => $all,
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
                'manage docs',
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
            // Signup users must complete the personal finance flow without admin access.
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
    }

    public function signupRole(): string
    {
        return 'user';
    }

    /**
     * @return array{ok:bool, message:string, permissions:int, roles:int}
     */
    public function syncPermissionsAndRoles(): array
    {
        if (!$this->tablesReady()) {
            return [
                'ok' => false,
                'message' => 'Permission tables are not ready.',
                'permissions' => 0,
                'roles' => 0,
            ];
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = $this->permissions();
        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $roleMap = $this->roleMap();
        $rolesSynced = 0;
        foreach ($roleMap as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissionNames);
            $rolesSynced++;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'ok' => true,
            'message' => 'Permissions and roles synced.',
            'permissions' => count($permissions),
            'roles' => $rolesSynced,
        ];
    }

    /**
     * @param array<int, array{name:string,email:string,password:string,role:string}> $users
     * @return array{created:int,updated:int}
     */
    public function ensureUsers(array $users): array
    {
        $result = ['created' => 0, 'updated' => 0];

        foreach ($users as $payload) {
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            if ($email === '') {
                continue;
            }

            $record = [
                'name' => trim((string) ($payload['name'] ?? 'Admin User')),
                'email' => $email,
                'password' => (string) ($payload['password'] ?? 'Admin@12345'),
                'role' => trim((string) ($payload['role'] ?? 'super-admin')) ?: 'super-admin',
                'receive_in_app_notifications' => true,
                'receive_telegram_notifications' => true,
                'browser_notifications_enabled' => true,
            ];

            /** @var User|null $user */
            $user = User::query()->where('email', $email)->first();
            if ($user) {
                $user->fill($record);
                $user->save();
                $result['updated']++;
            } else {
                $user = User::create($record);
                $result['created']++;
            }

            $this->syncUserRole($user, (string) $record['role']);
        }

        return $result;
    }

    public function syncUserRole(User $user, ?string $fallbackRole = null): void
    {
        if (!$this->tablesReady()) {
            return;
        }

        $roleMap = $this->roleMap();
        $targetRole = trim((string) ($user->role ?: ($fallbackRole ?: $this->signupRole())));

        if ($targetRole === '' || !array_key_exists($targetRole, $roleMap)) {
            $targetRole = $this->signupRole();
        }

        if (!Role::query()->where('name', $targetRole)->exists()) {
            return;
        }

        $user->syncRoles([$targetRole]);
        if ($user->role !== $targetRole) {
            $user->forceFill(['role' => $targetRole])->save();
        }
    }

    private function tablesReady(): bool
    {
        try {
            $tables = config('permission.table_names', []);
            if (!is_array($tables) || empty($tables)) {
                return false;
            }

            $required = ['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'];
            foreach ($required as $key) {
                if (empty($tables[$key])) {
                    return false;
                }
            }

            return Schema::hasTable((string) $tables['permissions'])
                && Schema::hasTable((string) $tables['roles'])
                && Schema::hasTable((string) $tables['model_has_permissions'])
                && Schema::hasTable((string) $tables['model_has_roles'])
                && Schema::hasTable((string) $tables['role_has_permissions']);
        } catch (Throwable) {
            return false;
        }
    }
}
