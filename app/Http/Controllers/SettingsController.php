<?php

namespace App\Http\Controllers;

use App\Http\Services\AdvancedMLService;
use App\Support\AppSettings;
use App\Support\HealthCheckService;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;
use Throwable;

class SettingsController extends Controller
{
    public function index(): View
    {
        $viewer = request()->user();
        $dbConnectionInfo = $this->databaseConnectionInfo();
        $uiBranding = AppSettings::uiBranding();
        $notificationSoundUrl = AppSettings::resolveUiAsset(AppSettings::get('ui.notification_sound_url', ''));
        $roleOptions = $this->availableRoleNames();

        $fields = $this->fields();
        $values = $this->readEnvValues(array_keys($fields));

        foreach ($fields as $key => $meta) {
            if (($values[$key] ?? '') === '' && array_key_exists('default', $meta)) {
                $values[$key] = (string) $meta['default'];
            }
        }

        $canManageSettings = (bool) ($viewer && $viewer->can('manage settings'));

        return view('settings.index', [
            'fields'      => $fields,
            'values'      => $values,
            'sections'    => $this->sections(),
            'envWritable' => $this->envWritable(),
            'canManageSettings' => $canManageSettings,
            'dbConnectionInfo' => $dbConnectionInfo,
            'uiBranding' => $uiBranding,
            'notificationSoundUrl' => $notificationSoundUrl,
            'roleOptions' => $roleOptions,
        ]);
    }

    public function media(Request $request): View
    {
        $this->assertCan($request, 'view settings', 'You do not have permission to view the media manager.');

        $folder = trim(str_replace('\\', '/', (string) $request->query('folder', '')), '/');
        $defaultDisk = trim((string) config('filesystems.default', 'local'));
        $s3Bucket = trim((string) config('filesystems.disks.s3.bucket', ''));
        $usingS3 = $defaultDisk === 's3' && $s3Bucket !== '';

        return view('settings.media', [
            'initialFolder' => $folder,
            'storageLabel' => $usingS3 ? ('AWS S3: ' . $s3Bucket) : 'Local /public/uploads',
            'storageDisk' => $usingS3 ? 's3' : 'public',
            'canManageSettings' => (bool) ($request->user() && $request->user()->can('manage settings')),
        ]);
    }

    public function searchIndex(Request $request): View
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can manage global search.');

        $entries = $this->activeSearchRegistryEntries();
        $catalog = $this->searchCatalogEntries($entries);
        $debounceMs = max(80, min((int) AppSettings::get('search.debounce_ms', '180'), 1500));

        return view('settings.search-index', [
            'entries' => $entries,
            'searchCatalog' => $catalog,
            'searchDebounceMs' => $debounceMs,
        ]);
    }

    public function searchCreate(Request $request): View
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can manage global search.');

        $entries = $this->activeSearchRegistryEntries();
        $catalog = $this->searchCatalogEntries($entries);

        return view('settings.search-form', [
            'mode' => 'create',
            'entry' => null,
            'searchCatalog' => $catalog,
            'formAction' => route('settings.search.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function searchEdit(Request $request, string $searchKey): View|RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can manage global search.');

        $entries = $this->activeSearchRegistryEntries();
        $entry = $this->findSearchRegistryEntry($entries, $searchKey);
        if ($entry === null) {
            return redirect()->route('settings.search.index')->with('error', 'Search source was not found.');
        }

        $catalog = $this->searchCatalogEntries($entries);

        return view('settings.search-form', [
            'mode' => 'edit',
            'entry' => $entry,
            'searchCatalog' => $catalog,
            'formAction' => route('settings.search.update', ['searchKey' => $entry['key']]),
            'formMethod' => 'PUT',
        ]);
    }

    public function storeSearch(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can manage global search.');

        $entries = $this->activeSearchRegistryEntries();
        $catalog = $this->searchCatalogEntries($entries);
        $entry = $this->searchEntryFromRequest($request, $catalog);
        if ($entry === null) {
            return back()->withInput();
        }

        $index = $this->findSearchRegistryEntryIndex($entries, (string) $entry['key']);
        if ($index !== null) {
            return back()->withInput()->with('error', 'Search key already exists. Use edit to update it.');
        }

        $entries[] = $entry;
        $this->persistSearchRegistryEntries($entries);

        return redirect()->route('settings.search.index')->with('success', 'Global search source created successfully.');
    }

    public function updateSearch(Request $request, string $searchKey): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can manage global search.');

        $entries = $this->activeSearchRegistryEntries();
        $editIndex = $this->findSearchRegistryEntryIndex($entries, $searchKey);
        if ($editIndex === null) {
            return redirect()->route('settings.search.index')->with('error', 'Search source was not found.');
        }

        $catalog = $this->searchCatalogEntries($entries);
        $entry = $this->searchEntryFromRequest($request, $catalog);
        if ($entry === null) {
            return back()->withInput();
        }

        $conflictIndex = $this->findSearchRegistryEntryIndex($entries, (string) $entry['key']);
        if ($conflictIndex !== null && $conflictIndex !== $editIndex) {
            return back()->withInput()->with('error', 'Search key already exists on another source.');
        }

        $entries[$editIndex] = $entry;
        $this->persistSearchRegistryEntries($entries);

        return redirect()->route('settings.search.index')->with('success', 'Global search source updated successfully.');
    }

    public function deleteSearch(Request $request, string $searchKey): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can manage global search.');

        $entries = $this->activeSearchRegistryEntries();
        $index = $this->findSearchRegistryEntryIndex($entries, $searchKey);
        if ($index === null) {
            return back()->with('error', 'Search source was not found.');
        }

        array_splice($entries, $index, 1);
        $this->persistSearchRegistryEntries($entries);

        return back()->with('success', 'Global search source deleted successfully.');
    }

    public function updateSearchDebounce(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can manage global search.');

        $validated = $request->validate([
            'search_debounce_ms' => ['required', 'integer', 'between:80,1500'],
        ]);

        AppSettings::putMany([
            'search.debounce_ms' => (string) $validated['search_debounce_ms'],
        ]);

        return back()->with('success', 'Global search debounce updated successfully.');
    }

    public function users(Request $request): View
    {
        $viewer = $request->user();
        $this->assertCan($request, 'view users', 'You do not have permission to view users.');

        $permissionTablesReady = $this->permissionTablesReady();
        $roles = $this->availableRoleNames();
        $permissions = $this->availablePermissionNames();
        $canManageUsers = (bool) ($viewer && $viewer->can('manage users'));
        $accessModules = $this->accessModules();

        $query = User::query()->orderBy('name');
        if ($permissionTablesReady) {
            $query->with(['roles:id,name', 'permissions:id,name']);
        }
        $users = $query->get();

        $userPayload = [];
        foreach ($users as $managedUser) {
            $currentRole = $permissionTablesReady
                ? ($managedUser->roles->first()->name ?? $managedUser->role ?? 'user')
                : ($managedUser->role ?? 'user');

            $directPermissions = $permissionTablesReady
                ? $managedUser->permissions->pluck('name')->values()->all()
                : [];

            $moduleAccess = [];
            foreach ($accessModules as $moduleKey => $module) {
                if (in_array($module['manage_permission'], $directPermissions, true)) {
                    $moduleAccess[$moduleKey] = 'manage';
                } elseif (in_array($module['view_permission'], $directPermissions, true)) {
                    $moduleAccess[$moduleKey] = 'view';
                } else {
                    $moduleAccess[$moduleKey] = 'none';
                }
            }

            $userPayload[$managedUser->id] = [
                'id' => $managedUser->id,
                'name' => (string) $managedUser->name,
                'email' => (string) $managedUser->email,
                'role' => (string) $currentRole,
                'telegram_chat_id' => (string) ($managedUser->telegram_chat_id ?? ''),
                'receive_in_app_notifications' => (bool) $managedUser->receive_in_app_notifications,
                'receive_telegram_notifications' => (bool) $managedUser->receive_telegram_notifications,
                'browser_notifications_enabled' => (bool) $managedUser->browser_notifications_enabled,
                'permissions' => $directPermissions,
                'module_access' => $moduleAccess,
            ];
        }

        return view('settings.users', [
            'users' => $users,
            'roles' => $roles,
            'permissionOptions' => $permissions,
            'accessModules' => $accessModules,
            'userPayload' => $userPayload,
            'hasSpatiePermissions' => $permissionTablesReady,
            'canManageUsers' => $canManageUsers,
        ]);
    }

    public function rbac(Request $request): View
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can manage RBAC.');

        $permissionTablesReady = $this->permissionTablesReady();
        $roles = collect();
        $roleNames = $this->availableRoleNames();
        $permissionOptions = collect($this->availablePermissionNames());
        $roleAccessMap = [];
        $roleExtraPermissionMap = [];
        $roleCatalog = collect();
        $accessModules = $this->accessModules();
        $modulePermissionNames = $this->modulePermissionNames($accessModules);

        if ($permissionTablesReady) {
            $roles = Role::query()->with('permissions')->orderBy('name')->get();
            $roleUserCounts = $this->roleUserCounts();
            $roleNames = $roles->pluck('name')->values()->all();
            $roleAccessMap = $this->buildRoleAccessMap($roles, $accessModules);
            $roleExtraPermissionMap = $this->buildRoleExtraPermissionMap($roles, $modulePermissionNames);
            $roleCatalog = $roles->map(function (Role $role) use ($roleUserCounts) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name')->values()->all(),
                    'permissions_count' => $role->permissions->count(),
                    'users_count' => (int) ($roleUserCounts[$role->id] ?? 0),
                ];
            })->values();
        }

        return view('settings.rbac', [
            'hasSpatiePermissions' => $permissionTablesReady,
            'roles' => $roles,
            'roleNames' => $roleNames,
            'permissionOptions' => $permissionOptions,
            'accessModules' => $accessModules,
            'modulePermissionNames' => $modulePermissionNames,
            'roleAccessMap' => $roleAccessMap,
            'roleExtraPermissionMap' => $roleExtraPermissionMap,
            'roleCatalog' => $roleCatalog,
            'protectedRoleNames' => $this->protectedRoleNames(),
        ]);
    }

    public function rbacCreate(Request $request): View
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can manage RBAC.');

        $permissionTablesReady = $this->permissionTablesReady();
        $permissionOptions = collect($this->availablePermissionNames());

        return view('settings.rbac-create', [
            'hasSpatiePermissions' => $permissionTablesReady,
            'permissionOptions' => $permissionOptions,
            'accessModules' => $this->accessModules(),
            'protectedRoleNames' => $this->protectedRoleNames(),
        ]);
    }

    public function rbacEdit(Request $request, Role $role): View|RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can manage RBAC.');

        if (!$this->permissionTablesReady()) {
            return redirect()->route('settings.rbac')->with('error', 'Spatie permission tables are not ready yet.');
        }

        $role->load('permissions');
        $roleUserCounts = $this->roleUserCounts();

        return view('settings.rbac-edit', [
            'hasSpatiePermissions' => true,
            'role' => $role,
            'permissionOptions' => collect($this->availablePermissionNames()),
            'accessModules' => $this->accessModules(),
            'protectedRoleNames' => $this->protectedRoleNames(),
            'assignedUsers' => (int) ($roleUserCounts[$role->id] ?? 0),
        ]);
    }

    public function exportUsers(Request $request)
    {
        $this->assertCan($request, 'manage users', 'Only authorized users can export accounts.');

        $permissionTablesReady = $this->permissionTablesReady();
        $query = User::query()->orderBy('id');
        if ($permissionTablesReady) {
            $query->with(['roles:id,name', 'permissions:id,name']);
        }

        $rows = $query->get()->map(function (User $user) use ($permissionTablesReady) {
            $roleName = $permissionTablesReady
                ? (optional($user->roles->first())->name ?: ($user->role ?: 'user'))
                : ($user->role ?: 'user');
            $permissions = $permissionTablesReady
                ? $user->permissions->pluck('name')->implode(', ')
                : '';

            return [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'password' => '',
                'role' => (string) $roleName,
                'telegram_chat_id' => (string) ($user->telegram_chat_id ?? ''),
                'receive_in_app_notifications' => $user->receive_in_app_notifications ? '1' : '0',
                'receive_telegram_notifications' => $user->receive_telegram_notifications ? '1' : '0',
                'browser_notifications_enabled' => $user->browser_notifications_enabled ? '1' : '0',
                'permissions' => $permissions,
            ];
        })->values()->all();

        $headings = [
            'name',
            'email',
            'password',
            'role',
            'telegram_chat_id',
            'receive_in_app_notifications',
            'receive_telegram_notifications',
            'browser_notifications_enabled',
            'permissions',
        ];

        $export = new class($rows, $headings) implements FromArray, WithHeadings
        {
            /**
             * @param array<int, array<string, string>> $rows
             * @param array<int, string> $headings
             */
            public function __construct(private array $rows, private array $headings) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        };

        $filename = 'haarray-users-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download($export, $filename);
    }

    public function importUsers(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage users', 'Only authorized users can import accounts.');

        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $file = $validated['import_file'];

        try {
            $import = new class implements ToArray
            {
                /**
                 * @param mixed $sheet
                 * @return array<int, array<int, mixed>>
                 */
                public function array($sheet): array
                {
                    return $sheet->toArray();
                }
            };

            $sheets = Excel::toArray($import, $file);
            $rows = $sheets[0] ?? [];
        } catch (Throwable $exception) {
            return back()->with('error', 'Import failed: ' . $exception->getMessage());
        }

        if (count($rows) < 2) {
            return back()->with('error', 'Import file must include a header row and at least one data row.');
        }

        $headerRow = array_shift($rows);
        if (!is_array($headerRow) || empty($headerRow)) {
            return back()->with('error', 'Invalid import header row.');
        }

        $headers = [];
        foreach ($headerRow as $index => $label) {
            $headers[(int) $index] = $this->normalizeImportHeading((string) $label, (int) $index);
        }

        $availableRoles = $this->availableRoleNames();
        $availableRoleSet = array_fill_keys($availableRoles, true);
        $permissionTablesReady = $this->permissionTablesReady();
        $availablePermissions = $this->availablePermissionNames();
        $availablePermissionSet = array_fill_keys($availablePermissions, true);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }

            $item = [];
            foreach ($headers as $index => $key) {
                $item[$key] = trim((string) ($row[$index] ?? ''));
            }

            $email = strtolower(trim((string) ($item['email'] ?? '')));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $skipped++;
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                $name = Str::headline(Str::before($email, '@'));
            }

            $roleName = strtolower(trim((string) ($item['role'] ?? 'user')));
            if ($roleName === '' || !isset($availableRoleSet[$roleName])) {
                $roleName = 'user';
            }
            $roleName = $this->normalizeRoleForEmail($email, $roleName, $availableRoles);

            $existing = User::query()->where('email', $email)->first();
            $isNew = !$existing;
            $user = $existing ?: new User();

            $user->email = $email;
            $user->name = $name;
            $user->role = $roleName;
            $user->telegram_chat_id = trim((string) ($item['telegram_chat_id'] ?? '')) ?: null;

            $user->receive_in_app_notifications = array_key_exists('receive_in_app_notifications', $item)
                ? $this->parseImportBoolean((string) $item['receive_in_app_notifications'])
                : ($isNew ? true : (bool) $user->receive_in_app_notifications);
            $user->receive_telegram_notifications = array_key_exists('receive_telegram_notifications', $item)
                ? $this->parseImportBoolean((string) $item['receive_telegram_notifications'])
                : (bool) $user->receive_telegram_notifications;
            $user->browser_notifications_enabled = array_key_exists('browser_notifications_enabled', $item)
                ? $this->parseImportBoolean((string) $item['browser_notifications_enabled'])
                : (bool) $user->browser_notifications_enabled;

            $password = trim((string) ($item['password'] ?? ''));
            if ($password !== '') {
                $user->password = $password;
            } elseif ($isNew) {
                $user->password = Str::random(16);
            }

            $user->save();

            if ($permissionTablesReady) {
                $user->syncRoles([$roleName]);

                if (array_key_exists('permissions', $item)) {
                    $rawPermissions = preg_split('/[,\|]/', (string) ($item['permissions'] ?? '')) ?: [];
                    $normalized = array_values(array_filter(array_map(
                        fn ($permission) => trim((string) $permission),
                        $rawPermissions
                    ), fn (string $permission) => $permission !== '' && isset($availablePermissionSet[$permission])));
                    $user->syncPermissions($normalized);
                }
            }

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        if ($permissionTablesReady) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return back()->with(
            'success',
            "User import complete. Created {$created}, updated {$updated}, skipped {$skipped}."
        );
    }

    public function exportActivity(Request $request)
    {
        $this->assertCan($request, 'view settings', 'You do not have permission to export activity logs.');

        $rows = $this->recentActivities(2000)->map(function (UserActivity $activity) {
            return [
                'datetime' => optional($activity->created_at)->format('Y-m-d H:i:s'),
                'user' => (string) ($activity->user->name ?? 'Unknown'),
                'email' => (string) ($activity->user->email ?? ''),
                'method' => strtoupper((string) $activity->method),
                'path' => (string) $activity->path,
                'route_name' => (string) ($activity->route_name ?: ''),
                'status' => (string) (data_get($activity->meta, 'status', '')),
                'ip_address' => (string) ($activity->ip_address ?? ''),
            ];
        })->values()->all();

        $headings = ['datetime', 'user', 'email', 'method', 'path', 'route_name', 'status', 'ip_address'];

        $export = new class($rows, $headings) implements FromArray, WithHeadings
        {
            /**
             * @param array<int, array<string, string|null>> $rows
             * @param array<int, string> $headings
             */
            public function __construct(private array $rows, private array $headings) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        };

        $filename = 'haarray-activity-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download($export, $filename);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can change system settings.');

        if (!$this->envWritable()) {
            return back()->with('error', 'The .env file is not writable. Update file permissions and try again.');
        }

        $fields = $this->fields();
        $validated = $request->validate($this->rules($fields));

        $updates = [];
        foreach ($fields as $key => $meta) {
            $rawValue = (string) ($validated[$key] ?? '');
            $updates[$key] = $this->formatEnvValue($rawValue, $meta['type'] ?? 'text');
        }

        try {
            $this->writeEnvValues($updates);
            Artisan::call('config:clear');
        } catch (Throwable $e) {
            return back()->with('error', 'Settings could not be saved: ' . $e->getMessage());
        }

        return back()->with('success', 'Environment settings were updated successfully.');
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can change branding settings.');

        $validated = $request->validate([
            'ui_display_name' => ['required', 'string', 'max:120'],
            'ui_brand_subtitle' => ['nullable', 'string', 'max:120'],
            'ui_brand_mark' => ['required', 'string', 'max:8'],
            'ui_theme_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'ui_logo_url' => ['nullable', 'string', 'max:2048'],
            'ui_favicon_url' => ['nullable', 'string', 'max:2048'],
            'ui_app_icon_url' => ['nullable', 'string', 'max:2048'],
            'ui_notification_sound_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $logoUrl = $this->normalizeUiAssetInput((string) ($validated['ui_logo_url'] ?? ''));
        $faviconUrl = $this->normalizeUiAssetInput((string) ($validated['ui_favicon_url'] ?? ''));
        $appIconUrl = $this->normalizeUiAssetInput((string) ($validated['ui_app_icon_url'] ?? ''));
        $notificationSoundUrl = $this->normalizeUiAssetInput((string) ($validated['ui_notification_sound_url'] ?? ''));

        AppSettings::putMany([
            'ui.display_name' => trim((string) $validated['ui_display_name']),
            'ui.brand_subtitle' => trim((string) ($validated['ui_brand_subtitle'] ?? '')),
            'ui.brand_mark' => trim((string) $validated['ui_brand_mark']),
            'ui.theme_color' => strtolower((string) $validated['ui_theme_color']),
            'ui.logo_url' => $logoUrl,
            'ui.favicon_url' => $faviconUrl,
            'ui.app_icon_url' => $appIconUrl,
            'ui.notification_sound_url' => $notificationSoundUrl,
        ]);

        return back()->with('success', 'Branding and asset settings updated successfully.');
    }

    public function updateSearchRegistry(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can change global search settings.');

        $validated = $request->validate([
            'search_registry_json' => ['nullable', 'string', 'max:120000'],
            'search_debounce_ms' => ['nullable', 'integer', 'between:80,1500'],
        ]);

        $raw = trim((string) ($validated['search_registry_json'] ?? ''));
        $debounceMs = (string) max(80, min((int) ($validated['search_debounce_ms'] ?? 180), 1500));

        if ($raw === '') {
            AppSettings::putMany([
                'search.registry_json' => '',
                'search.debounce_ms' => $debounceMs,
            ]);

            return back()->with('success', 'Global search registry reset to default config models.');
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            return back()->with('error', 'Global search JSON is invalid: ' . $exception->getMessage());
        }

        if (!is_array($decoded)) {
            return back()->with('error', 'Global search JSON must decode into an array.');
        }

        $normalized = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $modelClass = trim((string) ($row['model'] ?? ''));
            if ($modelClass === '' || !class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
                return back()->with('error', "Model class {$modelClass} is invalid for search registry.");
            }

            $searchFields = array_values(array_filter(array_map(function ($field) {
                $clean = trim((string) $field);
                return preg_match('/^[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)?$/', $clean) === 1 ? $clean : '';
            }, (array) ($row['search'] ?? []))));
            if (empty($searchFields)) {
                continue;
            }

            $idField = trim((string) ($row['id'] ?? 'id'));
            if ($idField === '') {
                $idField = 'id';
            }
            $titleField = trim((string) ($row['title'] ?? ($searchFields[0] ?? $idField)));
            if ($titleField === '') {
                $titleField = $idField;
            }

            $normalized[] = [
                'key' => trim((string) ($row['key'] ?? Str::snake(class_basename($modelClass)))),
                'model' => $modelClass,
                'id' => $idField,
                'title' => $titleField,
                'subtitle' => trim((string) ($row['subtitle'] ?? '')),
                'search' => array_values(array_unique($searchFields)),
                'route' => trim((string) ($row['route'] ?? '')),
                'query' => trim((string) ($row['query'] ?? '')),
                'permission' => trim((string) ($row['permission'] ?? '')),
                'icon' => trim((string) ($row['icon'] ?? 'fa-solid fa-magnifying-glass')),
            ];
        }

        AppSettings::putMany([
            'search.registry_json' => json_encode($normalized, JSON_UNESCAPED_SLASHES),
            'search.debounce_ms' => $debounceMs,
        ]);

        return back()->with('success', 'Global search registry updated successfully.');
    }

    public function deleteBrandAsset(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can remove branding assets.');

        $validated = $request->validate([
            'asset_path' => ['nullable', 'string', 'max:255'],
            'asset_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $relativePath = ltrim(str_replace('\\', '/', (string) ($validated['asset_path'] ?? '')), '/');
        if ($relativePath === '') {
            $assetUrl = trim((string) ($validated['asset_url'] ?? ''));
            $parsedPath = trim((string) parse_url($assetUrl, PHP_URL_PATH));
            $relativePath = ltrim($parsedPath, '/');
        }

        if ($relativePath === '') {
            return back()->with('error', 'No media path was provided for delete action.');
        }

        if (!str_starts_with($relativePath, 'uploads/')) {
            return back()->with('error', 'Invalid branding asset path.');
        }

        $absolutePath = public_path($relativePath);
        $realPublic = realpath(public_path()) ?: public_path();
        $realFile = realpath($absolutePath);

        if ($realFile !== false && str_starts_with($realFile, $realPublic) && File::exists($realFile)) {
            File::delete($realFile);
        } elseif (File::exists($absolutePath)) {
            File::delete($absolutePath);
        }

        $branding = AppSettings::uiBranding();
        $updates = [];
        if (ltrim((string) ($branding['logo_url'] ?? ''), '/') === $relativePath) {
            $updates['ui.logo_url'] = '';
        }
        if (ltrim((string) ($branding['favicon_url'] ?? ''), '/') === $relativePath) {
            $updates['ui.favicon_url'] = '';
        }
        if (ltrim((string) ($branding['app_icon_url'] ?? ''), '/') === $relativePath) {
            $updates['ui.app_icon_url'] = '';
        }
        if (ltrim((string) $this->normalizeUiAssetInput(AppSettings::get('ui.notification_sound_url', '')), '/') === $relativePath) {
            $updates['ui.notification_sound_url'] = '';
        }

        if (!empty($updates)) {
            AppSettings::putMany($updates);
        }

        return back()->with('success', 'Media file removed successfully.');
    }

    public function updateUserAccess(Request $request, User $user): RedirectResponse
    {
        $this->assertCan($request, 'manage users', 'Only users with access-management rights can update user permissions.');

        $roles = $this->availableRoleNames();
        $permissions = $this->availablePermissionNames();
        $permissionItemRules = ['string'];
        if (!empty($permissions)) {
            $permissionItemRules[] = Rule::in($permissions);
        } else {
            $permissionItemRules[] = 'max:120';
        }

        $rawPermissions = $request->input('permissions', []);
        if (is_string($rawPermissions)) {
            $rawPermissions = explode(',', $rawPermissions);
        }
        if (!is_array($rawPermissions)) {
            $rawPermissions = [];
        }

        $request->merge([
            'permissions' => array_values(array_filter(array_map(
                fn ($permission) => trim((string) $permission),
                $rawPermissions
            ))),
        ]);

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in($roles)],
            'receive_in_app_notifications' => ['nullable', 'boolean'],
            'receive_telegram_notifications' => ['nullable', 'boolean'],
            'browser_notifications_enabled' => ['nullable', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => $permissionItemRules,
            'module_access' => ['nullable', 'array'],
            'module_access.*' => ['nullable', 'string', Rule::in(['none', 'view', 'manage'])],
        ]);

        $modulePermissions = $this->moduleAccessPermissions((array) ($validated['module_access'] ?? []));
        $directPermissions = array_values(array_filter(array_map(
            fn ($permission) => trim((string) $permission),
            array_merge($validated['permissions'] ?? [], $modulePermissions)
        )));

        $roleName = $this->normalizeRoleForEmail((string) $user->email, (string) $validated['role'], $roles);

        $payload = [
            'role' => $roleName,
        ];

        if ($request->has('receive_in_app_notifications')) {
            $payload['receive_in_app_notifications'] = (bool) $request->boolean('receive_in_app_notifications');
        }

        if ($request->has('receive_telegram_notifications')) {
            $payload['receive_telegram_notifications'] = (bool) $request->boolean('receive_telegram_notifications');
        }

        if ($request->has('browser_notifications_enabled')) {
            $payload['browser_notifications_enabled'] = (bool) $request->boolean('browser_notifications_enabled');
        }

        if ($request->has('telegram_chat_id')) {
            $payload['telegram_chat_id'] = trim((string) ($validated['telegram_chat_id'] ?? '')) ?: null;
        }

        $user->update($payload);

        if ($this->permissionTablesReady()) {
            $user->syncRoles([$roleName]);
            $user->syncPermissions($directPermissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return back()->with('success', "Access settings updated for {$user->name}.");
    }

    public function updateRoleMatrix(Request $request): RedirectResponse|JsonResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can change role access matrix.');

        if (!$this->permissionTablesReady()) {
            return $this->roleMatrixResponse($request, false, 'Spatie permission tables are not ready. Run migrations first.', 422);
        }

        $roleModules = $request->input('role_modules', []);
        $extraPermissions = $request->input('extra_permissions', []);
        if (!is_array($roleModules)) {
            return $this->roleMatrixResponse($request, false, 'Invalid role matrix payload.', 422);
        }
        if (!is_array($extraPermissions)) {
            $extraPermissions = [];
        }

        $modules = $this->accessModules();
        $modulePermissionNames = $this->modulePermissionNames($modules);
        $allPermissions = Permission::query()->orderBy('name')->pluck('name')->all();
        $allowedPermissionSet = array_fill_keys($allPermissions, true);
        $roles = Role::query()->orderBy('name')->get();

        foreach ($roles as $role) {
            $moduleLevels = $roleModules[$role->name] ?? [];
            if (!is_array($moduleLevels)) {
                $moduleLevels = [];
            }
            $roleExtraPermissions = $extraPermissions[$role->name] ?? [];
            if (!is_array($roleExtraPermissions)) {
                $roleExtraPermissions = [];
            }

            $granted = [];

            foreach ($modules as $moduleKey => $meta) {
                $rawLevel = $moduleLevels[$moduleKey] ?? 'none';
                if (is_array($rawLevel)) {
                    $manageEnabled = filter_var($rawLevel['manage'] ?? false, FILTER_VALIDATE_BOOL);
                    $viewEnabled = filter_var($rawLevel['view'] ?? false, FILTER_VALIDATE_BOOL) || $manageEnabled;
                    $level = $manageEnabled ? 'manage' : ($viewEnabled ? 'view' : 'none');
                } else {
                    $level = strtolower((string) $rawLevel);
                    if ($level === 'active') {
                        $level = 'view';
                    } elseif ($level === 'inactive') {
                        $level = 'none';
                    }
                    if (!in_array($level, ['none', 'view', 'manage'], true)) {
                        $level = 'none';
                    }
                }

                if ($level === 'view' || $level === 'manage') {
                    $granted[] = $meta['view_permission'];
                }

                if ($level === 'manage') {
                    $granted[] = $meta['manage_permission'];
                }
            }

            foreach ($roleExtraPermissions as $permissionName) {
                $permission = trim((string) $permissionName);
                if ($permission === '') {
                    continue;
                }
                if (!isset($allowedPermissionSet[$permission])) {
                    continue;
                }
                if (in_array($permission, $modulePermissionNames, true)) {
                    continue;
                }
                $granted[] = $permission;
            }

            if ($role->name === 'admin') {
                $granted = $allPermissions;
            }

            $role->syncPermissions(array_values(array_unique($granted)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->roleMatrixResponse($request, true, 'Role access matrix updated successfully.');
    }

    private function roleMatrixResponse(Request $request, bool $ok, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
            ], $status);
        }

        return back()->with($ok ? 'success' : 'error', $message);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can create roles.');

        if (!$this->permissionTablesReady()) {
            return back()->with('error', 'Spatie permission tables are not ready yet.');
        }

        $allowedPermissions = $this->availablePermissionNames();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9 _-]+$/', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($allowedPermissions)],
        ]);

        $role = Role::create([
            'name' => trim((string) $validated['name']),
            'guard_name' => 'web',
        ]);

        $permissions = $this->normalizedPermissions($validated['permissions'] ?? [], $allowedPermissions);
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('settings.rbac.edit', $role)
            ->with('success', "Role {$role->name} created successfully.");
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can update roles.');

        if (!$this->permissionTablesReady()) {
            return back()->with('error', 'Spatie permission tables are not ready yet.');
        }

        $allowedPermissions = $this->availablePermissionNames();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9 _-]+$/', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($allowedPermissions)],
        ]);

        $oldName = $role->name;
        $newName = trim((string) $validated['name']);
        if (in_array($oldName, $this->protectedRoleNames(), true) && $oldName !== $newName) {
            return back()->with('error', "Role {$oldName} is protected and cannot be renamed.");
        }

        $role->name = $newName;
        $role->save();

        $permissions = $this->normalizedPermissions($validated['permissions'] ?? [], $allowedPermissions);
        $role->syncPermissions($permissions);

        if ($oldName !== $newName) {
            User::query()->where('role', $oldName)->update(['role' => $newName]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('settings.rbac.edit', $role)
            ->with('success', "Role {$role->name} updated successfully.");
    }

    public function deleteRole(Request $request, Role $role): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can delete roles.');

        if (!$this->permissionTablesReady()) {
            return back()->with('error', 'Spatie permission tables are not ready yet.');
        }

        if (in_array($role->name, $this->protectedRoleNames(), true)) {
            return back()->with('error', "Role {$role->name} is protected and cannot be deleted.");
        }

        $assignedUsers = (int) ($this->roleUserCounts()[$role->id] ?? 0);
        if ($assignedUsers > 0) {
            return back()->with('error', "Role {$role->name} is assigned to {$assignedUsers} user(s). Reassign them first.");
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('settings.rbac')->with('success', 'Role deleted successfully.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage users', 'Only authorized users can create accounts.');

        $availableRoles = $this->availableRoleNames();
        $availablePermissions = $this->availablePermissionNames();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'string', Rule::in($availableRoles)],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'receive_in_app_notifications' => ['nullable', 'boolean'],
            'receive_telegram_notifications' => ['nullable', 'boolean'],
            'browser_notifications_enabled' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($availablePermissions)],
            'module_access' => ['nullable', 'array'],
            'module_access.*' => ['nullable', 'string', Rule::in(['none', 'view', 'manage'])],
        ]);

        $roleName = trim((string) ($validated['role'] ?? ''));
        if ($roleName === '' || !in_array($roleName, $availableRoles, true)) {
            $roleName = in_array('user', $availableRoles, true)
                ? 'user'
                : (string) ($availableRoles[0] ?? 'user');
        }
        $roleName = $this->normalizeRoleForEmail((string) $validated['email'], $roleName, $availableRoles);

        $user = User::create([
            'name' => trim((string) $validated['name']),
            'email' => strtolower(trim((string) $validated['email'])),
            'password' => (string) $validated['password'],
            'role' => $roleName,
            'telegram_chat_id' => $validated['telegram_chat_id'] ?? null,
            'receive_in_app_notifications' => (bool) $request->boolean('receive_in_app_notifications'),
            'receive_telegram_notifications' => (bool) $request->boolean('receive_telegram_notifications'),
            'browser_notifications_enabled' => (bool) $request->boolean('browser_notifications_enabled'),
        ]);

        if ($this->permissionTablesReady()) {
            $user->syncRoles([$roleName]);
            $modulePermissions = $this->moduleAccessPermissions((array) ($validated['module_access'] ?? []));
            $permissions = $this->normalizedPermissions(array_merge($validated['permissions'] ?? [], $modulePermissions), $availablePermissions);
            $user->syncPermissions($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return back()->with('success', "User {$user->name} created successfully.");
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->assertCan($request, 'manage users', 'Only authorized users can update accounts.');

        $availableRoles = $this->availableRoleNames();
        $availablePermissions = $this->availablePermissionNames();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'string', Rule::in($availableRoles)],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'receive_in_app_notifications' => ['nullable', 'boolean'],
            'receive_telegram_notifications' => ['nullable', 'boolean'],
            'browser_notifications_enabled' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($availablePermissions)],
            'module_access' => ['nullable', 'array'],
            'module_access.*' => ['nullable', 'string', Rule::in(['none', 'view', 'manage'])],
        ]);

        $roleName = trim((string) ($validated['role'] ?? ($user->role ?? '')));
        if ($roleName === '' || !in_array($roleName, $availableRoles, true)) {
            $roleName = trim((string) ($user->role ?: ($availableRoles[0] ?? 'user')));
        }
        $roleName = $this->normalizeRoleForEmail((string) $validated['email'], $roleName, $availableRoles);

        $payload = [
            'name' => trim((string) $validated['name']),
            'email' => strtolower(trim((string) $validated['email'])),
            'role' => $roleName,
            'telegram_chat_id' => $validated['telegram_chat_id'] ?? null,
            'receive_in_app_notifications' => (bool) $request->boolean('receive_in_app_notifications'),
            'receive_telegram_notifications' => (bool) $request->boolean('receive_telegram_notifications'),
            'browser_notifications_enabled' => (bool) $request->boolean('browser_notifications_enabled'),
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = (string) $validated['password'];
        }

        $user->update($payload);

        if ($this->permissionTablesReady()) {
            if ($request->filled('role')) {
                $user->syncRoles([$roleName]);
            }

            if ($request->has('permissions') || $request->has('module_access')) {
                $modulePermissions = $this->moduleAccessPermissions((array) ($validated['module_access'] ?? []));
                $permissions = $this->normalizedPermissions(array_merge($validated['permissions'] ?? [], $modulePermissions), $availablePermissions);
                $user->syncPermissions($permissions);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return back()->with('success', "User {$user->name} updated successfully.");
    }

    public function updateUserProfile(Request $request, User $user): RedirectResponse
    {
        $this->assertCan($request, 'manage users', 'Only authorized users can update accounts.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $payload = [
            'name' => trim((string) $validated['name']),
            'email' => strtolower(trim((string) $validated['email'])),
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = (string) $validated['password'];
        }

        $user->update($payload);

        return back()->with('success', "User {$user->name} profile updated successfully.");
    }

    public function updateUserNotifications(Request $request, User $user): RedirectResponse
    {
        $this->assertCan($request, 'manage users', 'Only authorized users can update notification settings.');

        $validated = $request->validate([
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'receive_in_app_notifications' => ['nullable', 'boolean'],
            'receive_telegram_notifications' => ['nullable', 'boolean'],
            'browser_notifications_enabled' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'telegram_chat_id' => trim((string) ($validated['telegram_chat_id'] ?? '')) ?: null,
            'receive_in_app_notifications' => (bool) $request->boolean('receive_in_app_notifications'),
            'receive_telegram_notifications' => (bool) $request->boolean('receive_telegram_notifications'),
            'browser_notifications_enabled' => (bool) $request->boolean('browser_notifications_enabled'),
        ]);

        return back()->with('success', "Notification channels updated for {$user->name}.");
    }

    public function deleteUser(Request $request, User $user): RedirectResponse
    {
        $this->assertCan($request, 'manage users', 'Only authorized users can delete accounts.');

        if ((int) $request->user()?->id === (int) $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', "User {$name} deleted successfully.");
    }

    public function updateMySecurity(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $validated = $request->validate([
            'two_factor_enabled' => ['nullable', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'receive_in_app_notifications' => ['nullable', 'boolean'],
            'receive_telegram_notifications' => ['nullable', 'boolean'],
            'browser_notifications_enabled' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'two_factor_enabled' => (bool) $request->boolean('two_factor_enabled', false),
            'telegram_chat_id' => $validated['telegram_chat_id'] ?? null,
            'receive_in_app_notifications' => (bool) $request->boolean('receive_in_app_notifications', false),
            'receive_telegram_notifications' => (bool) $request->boolean('receive_telegram_notifications', false),
            'browser_notifications_enabled' => (bool) $request->boolean('browser_notifications_enabled', false),
        ]);

        return back()->with('success', 'Your security and notification preferences were updated.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'browser_notifications_enabled' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'name' => trim((string) $validated['name']),
            'email' => strtolower(trim((string) $validated['email'])),
            'telegram_chat_id' => trim((string) ($validated['telegram_chat_id'] ?? '')) ?: null,
            'browser_notifications_enabled' => (bool) $request->boolean('browser_notifications_enabled', false),
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = (string) $validated['password'];
        }

        $user->update($payload);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function upsertNotificationRule(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage notifications', 'Only authorized admins can manage notification automation rules.');

        $availableRoles = $this->availableRoleNames();
        $availableModels = array_keys($this->notificationAutomationModelOptions());

        $validated = $request->validate([
            'rule_id' => ['nullable', 'string', 'max:80'],
            'active' => ['nullable', 'boolean'],
            'model_key' => ['required', 'string', Rule::in($availableModels)],
            'event_name' => ['required', 'string', Rule::in(['activity.request'])],
            'methods' => ['nullable', 'array'],
            'methods.*' => ['string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'route_patterns' => ['nullable', 'string', 'max:400'],
            'title_template' => ['required', 'string', 'max:180'],
            'message_template' => ['required', 'string', 'max:1200'],
            'level' => ['required', Rule::in(['info', 'success', 'warning', 'error'])],
            'audience_type' => ['required', Rule::in(['admins', 'all', 'role', 'users'])],
            'audience_role' => ['nullable', 'string', Rule::in($availableRoles)],
            'audience_user_ids' => ['nullable', 'array'],
            'audience_user_ids.*' => ['integer', 'exists:users,id'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', Rule::in(['in_app', 'telegram'])],
            'throttle_seconds' => ['nullable', 'integer', 'between:10,3600'],
        ]);

        $rules = $this->notificationAutomationRules();
        $ruleId = trim((string) ($validated['rule_id'] ?? ''));
        if ($ruleId === '') {
            $ruleId = 'rule_' . Str::lower(Str::random(10));
        }

        $routePatterns = array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            preg_split('/[\r\n,]+/', (string) ($validated['route_patterns'] ?? '')) ?: []
        ), fn ($item) => $item !== ''));

        $payload = [
            'id' => $ruleId,
            'active' => (bool) $request->boolean('active', true),
            'model_key' => (string) $validated['model_key'],
            'event_name' => (string) $validated['event_name'],
            'methods' => array_values(array_unique((array) ($validated['methods'] ?? ['POST', 'PUT', 'PATCH', 'DELETE']))),
            'route_patterns' => $routePatterns,
            'title_template' => trim((string) $validated['title_template']),
            'message_template' => trim((string) $validated['message_template']),
            'level' => (string) $validated['level'],
            'channels' => array_values(array_unique((array) ($validated['channels'] ?? ['in_app']))),
            'throttle_seconds' => (int) ($validated['throttle_seconds'] ?? 60),
            'audience' => [
                'type' => (string) $validated['audience_type'],
                'role' => (string) ($validated['audience_role'] ?? ''),
                'user_ids' => array_values(array_map('intval', (array) ($validated['audience_user_ids'] ?? []))),
            ],
        ];

        $found = false;
        foreach ($rules as $index => $rule) {
            if ((string) ($rule['id'] ?? '') !== $ruleId) {
                continue;
            }
            $rules[$index] = $payload;
            $found = true;
            break;
        }
        if (!$found) {
            $rules[] = $payload;
        }

        $this->saveNotificationAutomationRules($rules);

        return back()->with('success', 'Notification automation rule saved.');
    }

    public function deleteNotificationRule(Request $request, string $ruleId): RedirectResponse
    {
        $this->assertCan($request, 'manage notifications', 'Only authorized admins can delete notification automation rules.');

        $ruleId = trim((string) $ruleId);
        if ($ruleId === '') {
            return back()->with('error', 'Invalid rule ID.');
        }

        $rules = $this->notificationAutomationRules();
        $filtered = array_values(array_filter($rules, fn ($rule) => (string) ($rule['id'] ?? '') !== $ruleId));
        $this->saveNotificationAutomationRules($filtered);

        return back()->with('success', 'Notification automation rule removed.');
    }

    public function runOpsAction(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can run maintenance actions.');

        if (!$this->opsUiEnabled()) {
            return back()->with('error', 'DevOps UI is disabled. Enable HAARRAY_ALLOW_SHELL_UI first.');
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in([
                'optimize_clear',
                'migrate_status',
                'fix_permissions',
                'clear_logs',
                'sync_permissions',
                'schedule_list',
                'schedule_run',
                'queue_restart',
                'queue_work_once',
                'queue_failed',
                'queue_flush',
            ])],
        ]);

        $action = (string) $validated['action'];

        $result = match ($action) {
            'optimize_clear' => $this->runShell('php artisan optimize:clear', 90),
            'migrate_status' => $this->runShell('php artisan migrate:status --no-ansi', 90),
            'fix_permissions' => $this->runShell('chmod -R 0777 storage bootstrap/cache public/uploads && chmod 0666 .env && chmod +x artisan server.php index.php', 30),
            'clear_logs' => $this->clearApplicationLogs(),
            'sync_permissions' => $this->runShell('php artisan haarray:permissions:sync --seed-admins --no-ansi', 90),
            'schedule_list' => $this->runShell('php artisan schedule:list --no-ansi', 90),
            'schedule_run' => $this->runShell('php artisan schedule:run --no-ansi', 120),
            'queue_restart' => $this->runShell('php artisan queue:restart --no-ansi', 60),
            'queue_work_once' => $this->runShell('php artisan queue:work --stop-when-empty --tries=1 --timeout=90 --no-ansi', 180),
            'queue_failed' => $this->runShell('php artisan queue:failed --no-ansi', 60),
            'queue_flush' => $this->runShell('php artisan queue:flush --no-ansi', 60),
            default => ['ok' => false, 'exit_code' => 1, 'output' => 'Invalid action.'],
        };

        $message = $result['ok']
            ? 'Maintenance action completed: ' . str_replace('_', ' ', $action) . '.'
            : 'Maintenance action failed: ' . str_replace('_', ' ', $action) . '.';

        return back()
            ->with($result['ok'] ? 'success' : 'error', $message)
            ->with('ops_output', (string) $result['output']);
    }

    public function runMlProbe(Request $request): RedirectResponse
    {
        $this->assertCan($request, 'manage settings', 'Only authorized admins can run ML diagnostics.');

        $validated = $request->validate([
            'food_ratio' => ['required', 'numeric', 'between:0,1'],
            'entertainment_ratio' => ['required', 'numeric', 'between:0,1'],
            'savings_rate' => ['required', 'numeric', 'between:0,1'],
        ]);

        /** @var AdvancedMLService $ml */
        $ml = app(AdvancedMLService::class);

        $result = $ml->classifySpendingProfile(
            (float) $validated['food_ratio'],
            (float) $validated['entertainment_ratio'],
            (float) $validated['savings_rate'],
        );

        return back()
            ->with('success', 'ML diagnostic probe completed.')
            ->with('ml_probe_result', [
                'input' => [
                    'food_ratio' => (float) $validated['food_ratio'],
                    'entertainment_ratio' => (float) $validated['entertainment_ratio'],
                    'savings_rate' => (float) $validated['savings_rate'],
                ],
                'output' => $result,
            ]);
    }

    /**
     * @return array<string, array{title: string, description: string}>
     */
    private function sections(): array
    {
        return [
            'app' => [
                'title'       => 'Application',
                'description' => 'Core app identity and runtime behavior.',
            ],
            'haarray' => [
                'title'       => 'Haarray Features',
                'description' => 'Branding and feature flags from config/haarray.php.',
            ],
            'telegram' => [
                'title'       => 'Telegram Bot API',
                'description' => 'Bot token, username, and webhook endpoint configuration.',
            ],
            'ml' => [
                'title'       => 'ML Suggestion Engine',
                'description' => 'Thresholds used by the suggestion service.',
            ],
            'realtime' => [
                'title'       => 'Realtime & Broadcast',
                'description' => 'Polling behavior now, plus Pusher keys for optional realtime transports.',
            ],
            'storage' => [
                'title'       => 'Storage',
                'description' => 'Media/file storage driver and optional AWS S3 credentials.',
            ],
            'database' => [
                'title'       => 'Database',
                'description' => 'Connection details used by Laravel database config.',
            ],
            'mail' => [
                'title'       => 'Mail',
                'description' => 'Mailer and sender details.',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fields(): array
    {
        return [
            'APP_NAME' => [
                'section'  => 'app',
                'label'    => 'App Name',
                'type'     => 'text',
                'required' => true,
                'default'  => 'Haarray Core',
            ],
            'APP_URL' => [
                'section'  => 'app',
                'label'    => 'App URL',
                'type'     => 'url',
                'required' => true,
                'default'  => 'http://localhost',
            ],
            'APP_ENV' => [
                'section'  => 'app',
                'label'    => 'App Environment',
                'type'     => 'select',
                'required' => true,
                'options'  => ['local', 'staging', 'production'],
                'default'  => 'local',
            ],
            'APP_DEBUG' => [
                'section'  => 'app',
                'label'    => 'Debug Mode',
                'type'     => 'bool',
                'required' => true,
                'default'  => 'true',
            ],
            'HAARRAY_HOT_RELOAD' => [
                'section'  => 'app',
                'label'    => 'Enable Dev Hot Reload (local only)',
                'type'     => 'bool',
                'required' => true,
                'default'  => 'true',
            ],
            'APP_TIMEZONE' => [
                'section'  => 'app',
                'label'    => 'Timezone',
                'type'     => 'text',
                'required' => true,
                'default'  => 'UTC',
            ],
            'HAARRAY_BRAND' => [
                'section'  => 'haarray',
                'label'    => 'Brand Name',
                'type'     => 'text',
                'required' => true,
                'default'  => 'Haarray',
            ],
            'HAARRAY_INITIAL' => [
                'section'  => 'haarray',
                'label'    => 'Brand Initial',
                'type'     => 'text',
                'required' => true,
                'default'  => 'H',
            ],
            'HAARRAY_SHOW_TG' => [
                'section'  => 'haarray',
                'label'    => 'Show Telegram Status',
                'type'     => 'bool',
                'required' => true,
                'default'  => 'false',
            ],
            'HAARRAY_PWA' => [
                'section'  => 'haarray',
                'label'    => 'Enable PWA',
                'type'     => 'bool',
                'required' => true,
                'default'  => 'true',
            ],
            'HAARRAY_ML' => [
                'section'  => 'haarray',
                'label'    => 'Enable ML Suggestions',
                'type'     => 'bool',
                'required' => true,
                'default'  => 'true',
            ],
            'TELEGRAM_BOT_TOKEN' => [
                'section'  => 'telegram',
                'label'    => 'Bot Token',
                'type'     => 'password',
                'required' => false,
                'default'  => '',
            ],
            'TELEGRAM_BOT_USERNAME' => [
                'section'  => 'telegram',
                'label'    => 'Bot Username',
                'type'     => 'text',
                'required' => false,
                'default'  => 'HariLogBot',
            ],
            'TELEGRAM_BOT_WEBHOOK_URL' => [
                'section'  => 'telegram',
                'label'    => 'Webhook URL',
                'type'     => 'url',
                'required' => false,
                'default'  => '',
            ],
            'HAARRAY_ML_IDLE_CASH_THRESHOLD' => [
                'section'  => 'ml',
                'label'    => 'Idle Cash Threshold (NPR)',
                'type'     => 'number',
                'required' => true,
                'default'  => '5000',
            ],
            'HAARRAY_ML_FOOD_BUDGET_WARNING' => [
                'section'  => 'ml',
                'label'    => 'Food Budget Warning Ratio',
                'type'     => 'decimal',
                'required' => true,
                'default'  => '0.35',
            ],
            'HAARRAY_ML_SAVINGS_RATE_TARGET' => [
                'section'  => 'ml',
                'label'    => 'Savings Rate Target',
                'type'     => 'decimal',
                'required' => true,
                'default'  => '0.30',
            ],
            'HAARRAY_ML_RETRAIN_DAYS' => [
                'section'  => 'ml',
                'label'    => 'Model Retrain Interval (days)',
                'type'     => 'number',
                'required' => true,
                'default'  => '7',
            ],
            'HAARRAY_NOTIFY_POLL_SECONDS' => [
                'section'  => 'realtime',
                'label'    => 'Notification Poll Interval (seconds)',
                'type'     => 'number',
                'required' => true,
                'default'  => '20',
            ],
            'BROADCAST_CONNECTION' => [
                'section'  => 'realtime',
                'label'    => 'Broadcast Driver',
                'type'     => 'select',
                'required' => true,
                'options'  => ['log', 'pusher', 'reverb', 'null'],
                'default'  => 'log',
            ],
            'PUSHER_APP_ID' => [
                'section'  => 'realtime',
                'label'    => 'Pusher App ID',
                'type'     => 'text',
                'required' => false,
                'default'  => '',
            ],
            'PUSHER_APP_KEY' => [
                'section'  => 'realtime',
                'label'    => 'Pusher App Key',
                'type'     => 'text',
                'required' => false,
                'default'  => '',
            ],
            'PUSHER_APP_SECRET' => [
                'section'  => 'realtime',
                'label'    => 'Pusher App Secret',
                'type'     => 'password',
                'required' => false,
                'default'  => '',
            ],
            'PUSHER_HOST' => [
                'section'  => 'realtime',
                'label'    => 'Pusher Host',
                'type'     => 'text',
                'required' => false,
                'default'  => '',
            ],
            'PUSHER_PORT' => [
                'section'  => 'realtime',
                'label'    => 'Pusher Port',
                'type'     => 'port',
                'required' => true,
                'default'  => '443',
            ],
            'PUSHER_SCHEME' => [
                'section'  => 'realtime',
                'label'    => 'Pusher Scheme',
                'type'     => 'select',
                'required' => true,
                'options'  => ['http', 'https'],
                'default'  => 'https',
            ],
            'PUSHER_APP_CLUSTER' => [
                'section'  => 'realtime',
                'label'    => 'Pusher Cluster',
                'type'     => 'text',
                'required' => false,
                'default'  => 'mt1',
            ],
            'PUSHER_USE_TLS' => [
                'section'  => 'realtime',
                'label'    => 'Pusher TLS',
                'type'     => 'bool',
                'required' => true,
                'default'  => 'true',
            ],
            'FILESYSTEM_DISK' => [
                'section'  => 'storage',
                'label'    => 'Filesystem Disk',
                'type'     => 'select',
                'required' => true,
                'options'  => ['local', 'public', 's3'],
                'default'  => 'local',
            ],
            'AWS_ACCESS_KEY_ID' => [
                'section'  => 'storage',
                'label'    => 'AWS Access Key ID',
                'type'     => 'text',
                'required' => false,
                'default'  => '',
            ],
            'AWS_SECRET_ACCESS_KEY' => [
                'section'  => 'storage',
                'label'    => 'AWS Secret Access Key',
                'type'     => 'password',
                'required' => false,
                'default'  => '',
            ],
            'AWS_DEFAULT_REGION' => [
                'section'  => 'storage',
                'label'    => 'AWS Region',
                'type'     => 'text',
                'required' => false,
                'default'  => 'us-east-1',
            ],
            'AWS_BUCKET' => [
                'section'  => 'storage',
                'label'    => 'AWS Bucket',
                'type'     => 'text',
                'required' => false,
                'default'  => '',
            ],
            'AWS_URL' => [
                'section'  => 'storage',
                'label'    => 'AWS URL (optional)',
                'type'     => 'url',
                'required' => false,
                'default'  => '',
            ],
            'AWS_ENDPOINT' => [
                'section'  => 'storage',
                'label'    => 'AWS Endpoint (optional)',
                'type'     => 'url',
                'required' => false,
                'default'  => '',
            ],
            'AWS_USE_PATH_STYLE_ENDPOINT' => [
                'section'  => 'storage',
                'label'    => 'AWS Path Style Endpoint',
                'type'     => 'bool',
                'required' => true,
                'default'  => 'false',
            ],
            'DB_HOST' => [
                'section'  => 'database',
                'label'    => 'DB Host',
                'type'     => 'text',
                'required' => true,
                'default'  => '127.0.0.1',
            ],
            'DB_PORT' => [
                'section'  => 'database',
                'label'    => 'DB Port',
                'type'     => 'port',
                'required' => true,
                'default'  => '3306',
            ],
            'DB_DATABASE' => [
                'section'  => 'database',
                'label'    => 'DB Database',
                'type'     => 'text',
                'required' => true,
                'default'  => 'harray_core',
            ],
            'DB_USERNAME' => [
                'section'  => 'database',
                'label'    => 'DB Username',
                'type'     => 'text',
                'required' => true,
                'default'  => 'root',
            ],
            'DB_PASSWORD' => [
                'section'  => 'database',
                'label'    => 'DB Password',
                'type'     => 'password',
                'required' => false,
                'default'  => '',
            ],
            'MAIL_MAILER' => [
                'section'  => 'mail',
                'label'    => 'Mail Driver',
                'type'     => 'select',
                'required' => true,
                'options'  => ['log', 'smtp', 'sendmail', 'mailgun'],
                'default'  => 'log',
            ],
            'MAIL_HOST' => [
                'section'  => 'mail',
                'label'    => 'Mail Host',
                'type'     => 'text',
                'required' => true,
                'default'  => '127.0.0.1',
            ],
            'MAIL_PORT' => [
                'section'  => 'mail',
                'label'    => 'Mail Port',
                'type'     => 'port',
                'required' => true,
                'default'  => '2525',
            ],
            'MAIL_USERNAME' => [
                'section'  => 'mail',
                'label'    => 'Mail Username',
                'type'     => 'text',
                'required' => false,
                'default'  => '',
            ],
            'MAIL_PASSWORD' => [
                'section'  => 'mail',
                'label'    => 'Mail Password',
                'type'     => 'password',
                'required' => false,
                'default'  => '',
            ],
            'MAIL_FROM_ADDRESS' => [
                'section'  => 'mail',
                'label'    => 'From Address',
                'type'     => 'email',
                'required' => true,
                'default'  => 'hello@example.com',
            ],
            'MAIL_FROM_NAME' => [
                'section'  => 'mail',
                'label'    => 'From Name',
                'type'     => 'text',
                'required' => true,
                'default'  => 'Haarray Core',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeSearchRegistryEntries(): array
    {
        $raw = AppSettings::get('search.registry_json', '');
        $entries = $this->resolvedSearchRegistryEntries($raw);

        $normalized = [];
        $seenKeys = [];
        foreach ($entries as $row) {
            if (!is_array($row)) {
                continue;
            }

            $modelClass = trim((string) ($row['model'] ?? ''));
            if ($modelClass === '' || !class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $searchFields = array_values(array_unique(array_filter(array_map(function ($field) {
                $clean = trim((string) $field);
                return preg_match('/^[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)?$/', $clean) === 1 ? $clean : '';
            }, (array) ($row['search'] ?? [])))));
            if (empty($searchFields)) {
                continue;
            }

            $baseKey = $this->normalizeSearchRegistryKey((string) ($row['key'] ?? ''), $modelClass);
            $key = $baseKey;
            $suffix = 2;
            while (isset($seenKeys[strtolower($key)])) {
                $key = $baseKey . '_' . $suffix;
                $suffix++;
            }
            $seenKeys[strtolower($key)] = true;

            $idField = trim((string) ($row['id'] ?? 'id'));
            if ($idField === '') {
                $idField = 'id';
            }

            $titleField = trim((string) ($row['title'] ?? ($searchFields[0] ?? $idField)));
            if ($titleField === '') {
                $titleField = $idField;
            }

            $normalized[] = [
                'key' => $key,
                'model' => $modelClass,
                'id' => $idField,
                'title' => $titleField,
                'subtitle' => trim((string) ($row['subtitle'] ?? '')),
                'search' => $searchFields,
                'route' => trim((string) ($row['route'] ?? '')),
                'query' => trim((string) ($row['query'] ?? '')),
                'permission' => trim((string) ($row['permission'] ?? '')),
                'icon' => trim((string) ($row['icon'] ?? 'fa-solid fa-magnifying-glass')),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function persistSearchRegistryEntries(array $entries): void
    {
        $encoded = json_encode(array_values($entries), JSON_UNESCAPED_SLASHES);
        AppSettings::putMany([
            'search.registry_json' => $encoded !== false ? $encoded : '[]',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>|null
     */
    private function findSearchRegistryEntry(array $entries, string $key): ?array
    {
        $index = $this->findSearchRegistryEntryIndex($entries, $key);
        if ($index === null) {
            return null;
        }

        return $entries[$index] ?? null;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function findSearchRegistryEntryIndex(array $entries, string $key): ?int
    {
        $needle = strtolower(trim($key));
        if ($needle === '') {
            return null;
        }

        foreach ($entries as $index => $entry) {
            $entryKey = strtolower(trim((string) ($entry['key'] ?? '')));
            if ($entryKey === $needle) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $catalog
     * @return array<string, mixed>|null
     */
    private function searchEntryFromRequest(Request $request, array $catalog): ?array
    {
        $validated = $request->validate([
            'model' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:120'],
            'id' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:120'],
            'search' => ['required', 'array', 'min:1'],
            'search.*' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)?$/'],
            'route' => ['nullable', 'string', 'max:190'],
            'query' => ['nullable', 'string', 'max:1900'],
            'permission' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:120'],
        ]);

        $modelClass = trim((string) ($validated['model'] ?? ''));
        $catalogItem = collect($catalog)->first(
            fn ($row) => (string) ($row['model'] ?? '') === $modelClass
        );
        if (!is_array($catalogItem)) {
            $request->session()->flash('error', 'Selected model is not available for global search.');
            return null;
        }

        $allowedFields = $this->catalogFieldPaths($catalogItem);
        if (empty($allowedFields)) {
            $request->session()->flash('error', 'Selected model has no searchable fields.');
            return null;
        }
        $allowedLookup = array_fill_keys($allowedFields, true);

        $idField = trim((string) ($validated['id'] ?? 'id'));
        if (!isset($allowedLookup[$idField])) {
            $request->session()->flash('error', 'ID field must be selected from allowed model fields.');
            return null;
        }

        $titleField = trim((string) ($validated['title'] ?? $idField));
        if (!isset($allowedLookup[$titleField])) {
            $request->session()->flash('error', 'Title field must be selected from allowed model fields.');
            return null;
        }

        $subtitleField = trim((string) ($validated['subtitle'] ?? ''));
        if ($subtitleField !== '' && !isset($allowedLookup[$subtitleField])) {
            $request->session()->flash('error', 'Subtitle field must be selected from allowed model fields.');
            return null;
        }

        $searchFields = array_values(array_unique(array_filter(array_map(function ($field) use ($allowedLookup) {
            $clean = trim((string) $field);
            if ($clean === '' || !isset($allowedLookup[$clean])) {
                return '';
            }

            return $clean;
        }, (array) ($validated['search'] ?? [])))));
        if (empty($searchFields)) {
            $request->session()->flash('error', 'Choose at least one valid searchable field.');
            return null;
        }

        return [
            'key' => $this->normalizeSearchRegistryKey((string) ($validated['key'] ?? ''), $modelClass),
            'model' => $modelClass,
            'id' => $idField,
            'title' => $titleField,
            'subtitle' => $subtitleField,
            'search' => $searchFields,
            'route' => trim((string) ($validated['route'] ?? '')),
            'query' => trim((string) ($validated['query'] ?? '')),
            'permission' => trim((string) ($validated['permission'] ?? '')),
            'icon' => trim((string) ($validated['icon'] ?? 'fa-solid fa-magnifying-glass')),
        ];
    }

    /**
     * @param array<string, mixed> $catalogItem
     * @return array<int, string>
     */
    private function catalogFieldPaths(array $catalogItem): array
    {
        $raw = array_merge(
            (array) ($catalogItem['columns'] ?? []),
            (array) ($catalogItem['relation_fields'] ?? [])
        );

        $clean = array_map(fn ($field) => trim((string) $field), $raw);
        return array_values(array_unique(array_filter($clean, fn ($field) => $field !== '')));
    }

    private function normalizeSearchRegistryKey(string $key, string $modelClass): string
    {
        $clean = strtolower(trim($key));
        $clean = preg_replace('/[^a-z0-9_-]+/', '_', $clean) ?? '';
        $clean = trim($clean, '_-');
        if ($clean !== '') {
            return $clean;
        }

        return Str::snake(class_basename($modelClass));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolvedSearchRegistryEntries(string $raw): array
    {
        $raw = trim($raw);
        if ($raw !== '') {
            try {
                $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return array_values(array_filter($decoded, fn ($item) => is_array($item)));
                }
            } catch (Throwable) {
                // Fall back to config defaults.
            }
        }

        $defaults = (array) config('haarray.global_search.models', []);
        return array_values(array_filter($defaults, fn ($item) => is_array($item)));
    }

    /**
     * @param array<int, array<string, mixed>> $activeEntries
     * @return array<int, array<string, mixed>>
     */
    private function searchCatalogEntries(array $activeEntries): array
    {
        $defaults = (array) config('haarray.global_search.models', []);
        $defaultEntries = array_values(array_filter($defaults, fn ($item) => is_array($item)));

        $modelClasses = [];
        foreach (array_merge($defaultEntries, $activeEntries) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $modelClass = trim((string) ($entry['model'] ?? ''));
            if ($modelClass === '' || !class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
                continue;
            }
            $modelClasses[$modelClass] = true;
        }
        foreach ($this->discoverSearchableModelClasses() as $modelClass) {
            $modelClasses[$modelClass] = true;
        }

        $catalog = [];
        foreach (array_keys($modelClasses) as $modelClass) {
            $entry = $this->describeSearchModel($modelClass);
            if ($entry === null) {
                continue;
            }

            $default = collect($defaultEntries)->first(fn ($row) => (string) ($row['model'] ?? '') === $modelClass) ?? [];
            $active = collect($activeEntries)->first(fn ($row) => (string) ($row['model'] ?? '') === $modelClass) ?? [];
            $idField = trim((string) ($entry['id_field'] ?? 'id'));
            $titleField = trim((string) ($entry['title_field'] ?? $idField));

            $entry['defaults'] = [
                'key' => trim((string) ($active['key'] ?? $default['key'] ?? Str::snake(class_basename($modelClass)))),
                'id' => trim((string) ($active['id'] ?? $default['id'] ?? $idField)),
                'title' => trim((string) ($active['title'] ?? $default['title'] ?? $titleField)),
                'subtitle' => trim((string) ($active['subtitle'] ?? $default['subtitle'] ?? '')),
                'route' => trim((string) ($active['route'] ?? $default['route'] ?? '')),
                'query' => trim((string) ($active['query'] ?? $default['query'] ?? '')),
                'permission' => trim((string) ($active['permission'] ?? $default['permission'] ?? '')),
                'icon' => trim((string) ($active['icon'] ?? $default['icon'] ?? 'fa-solid fa-magnifying-glass')),
                'search' => array_values(array_unique(array_filter(array_map(
                    fn ($field) => trim((string) $field),
                    (array) ($active['search'] ?? $default['search'] ?? [])
                )))),
            ];

            $catalog[] = $entry;
        }

        usort($catalog, fn ($a, $b) => strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? '')));

        return $catalog;
    }

    /**
     * @return array<int, string>
     */
    private function discoverSearchableModelClasses(): array
    {
        $modelsPath = app_path('Models');
        if (!is_dir($modelsPath)) {
            return [];
        }

        try {
            $files = File::allFiles($modelsPath);
        } catch (Throwable) {
            return [];
        }

        $classMap = [];
        $base = rtrim(str_replace('\\', '/', $modelsPath), '/') . '/';

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (!str_starts_with($path, $base) || !str_ends_with($path, '.php')) {
                continue;
            }

            $relative = substr($path, strlen($base), -4);
            if (!is_string($relative) || trim($relative) === '') {
                continue;
            }

            $class = 'App\\Models\\' . str_replace('/', '\\', $relative);
            if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
                continue;
            }

            $classMap[$class] = true;
        }

        $classes = array_keys($classMap);
        sort($classes);
        return $classes;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function describeSearchModel(string $modelClass): ?array
    {
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        /** @var Model $model */
        $model = new $modelClass();
        $table = trim((string) $model->getTable());
        if ($table === '' || !Schema::hasTable($table)) {
            return null;
        }

        $columns = $this->searchableColumnsForTable($table);
        if (empty($columns)) {
            return null;
        }

        $relations = $this->describeSearchModelRelations($model);
        $relationFields = [];
        foreach ($relations as $relation) {
            foreach ((array) ($relation['columns'] ?? []) as $column) {
                $relationFields[] = $relation['name'] . '.' . $column;
            }
        }

        $idField = in_array('id', $columns, true) ? 'id' : (string) ($columns[0] ?? 'id');
        $titleField = in_array('name', $columns, true)
            ? 'name'
            : (in_array('title', $columns, true) ? 'title' : $idField);
        $subtitleField = in_array('email', $columns, true)
            ? 'email'
            : (in_array('slug', $columns, true) ? 'slug' : '');

        return [
            'model' => $modelClass,
            'label' => class_basename($modelClass),
            'table' => $table,
            'columns' => $columns,
            'relation_fields' => $relationFields,
            'relations' => $relations,
            'id_field' => $idField,
            'title_field' => $titleField,
            'subtitle_field' => $subtitleField,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function searchableColumnsForTable(string $table): array
    {
        try {
            $columns = Schema::getColumnListing($table);
        } catch (Throwable) {
            return [];
        }

        $blocked = [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $filtered = array_values(array_filter(array_map(
            fn ($column) => trim((string) $column),
            $columns
        ), function (string $column) use ($blocked) {
            if ($column === '' || in_array($column, $blocked, true)) {
                return false;
            }

            return preg_match('/^[a-zA-Z0-9_]+$/', $column) === 1;
        }));

        return array_slice($filtered, 0, 30);
    }

    /**
     * @return array<int, array{name:string,label:string,model:string,columns:array<int,string>}>
     */
    private function describeSearchModelRelations(Model $model): array
    {
        $reflection = new \ReflectionClass($model);
        $relations = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $methodName = $method->getName();
            if (str_starts_with($methodName, 'scope') || str_starts_with($methodName, '__')) {
                continue;
            }

            try {
                $result = $method->invoke($model);
            } catch (Throwable) {
                continue;
            }

            if (!$result instanceof Relation) {
                continue;
            }

            $relatedModel = $result->getRelated();
            if (!$relatedModel instanceof Model) {
                continue;
            }

            $table = trim((string) $relatedModel->getTable());
            if ($table === '' || !Schema::hasTable($table)) {
                continue;
            }

            $columns = $this->searchableColumnsForTable($table);
            if (empty($columns)) {
                continue;
            }

            $relations[] = [
                'name' => $methodName,
                'label' => Str::headline($methodName),
                'model' => get_class($relatedModel),
                'columns' => array_slice($columns, 0, 16),
            ];
        }

        usort($relations, fn ($a, $b) => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        return $relations;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<int, mixed>>
     */
    private function rules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $key => $meta) {
            $type = $meta['type'] ?? 'text';
            $required = ($meta['required'] ?? false) ? 'required' : 'nullable';
            $options = $meta['options'] ?? [];

            $rules[$key] = match ($type) {
                'url'    => [$required, 'url', 'max:255'],
                'email'  => [$required, 'email', 'max:255'],
                'number' => [$required, 'integer', 'between:0,1000000000'],
                'port' => [$required, 'integer', 'between:1,65535'],
                'decimal' => [$required, 'numeric', 'between:0,100'],
                'bool'   => [$required, 'in:true,false,1,0,on,off'],
                'select' => array_merge([$required, 'string', 'max:255'], $options ? ['in:' . implode(',', $options)] : []),
                default  => [$required, 'string', 'max:255'],
            };
        }

        return $rules;
    }

    private function envPath(): string
    {
        return base_path('.env');
    }

    private function envWritable(): bool
    {
        $path = $this->envPath();
        if (!File::exists($path)) {
            return is_writable(base_path());
        }

        if (is_writable($path)) {
            return true;
        }

        @chmod($path, 0666);
        clearstatcache(true, $path);

        return is_writable($path);
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, string>
     */
    private function readEnvValues(array $keys): array
    {
        $path = $this->envPath();
        if (!File::exists($path) && File::exists(base_path('.env.example'))) {
            File::copy(base_path('.env.example'), $path);
        }

        $content = File::exists($path) ? File::get($path) : '';
        $values = [];

        foreach ($keys as $key) {
            $pattern = '/^' . preg_quote($key, '/') . '=(.*)$/m';

            if (preg_match($pattern, $content, $matches) === 1) {
                $values[$key] = $this->decodeEnvValue($matches[1]);
            } else {
                $values[$key] = '';
            }
        }

        return $values;
    }

    private function decodeEnvValue(string $value): string
    {
        $value = trim($value);

        if ($value === '' || strtolower($value) === 'null') {
            return '';
        }

        $startsWithQuote = Str::startsWith($value, '"') || Str::startsWith($value, "'");
        $endsWithQuote = Str::endsWith($value, '"') || Str::endsWith($value, "'");

        if ($startsWithQuote && $endsWithQuote) {
            $value = substr($value, 1, -1);
            $value = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
        }

        return $value;
    }

    private function formatEnvValue(string $value, string $type): string
    {
        if ($type === 'bool') {
            return filter_var($value, FILTER_VALIDATE_BOOL) ? 'true' : 'false';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|#|"|\'/', $value) === 1) {
            return '"' . addcslashes($value, "\\\"") . '"';
        }

        return $value;
    }

    /**
     * @param array<string, string> $updates
     */
    private function writeEnvValues(array $updates): void
    {
        $path = $this->envPath();

        if (!File::exists($path) && File::exists(base_path('.env.example'))) {
            File::copy(base_path('.env.example'), $path);
        }

        $content = File::exists($path) ? File::get($path) : '';

        foreach ($updates as $key => $value) {
            $line = $key . '=' . $value;
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $content) === 1) {
                $content = preg_replace($pattern, $line, $content, 1) ?? $content;
            } else {
                $content = rtrim($content) . PHP_EOL . $line . PHP_EOL;
            }
        }

        File::put($path, $content);
    }

    /**
     * @return array<string>
     */
    private function availableRoleNames(): array
    {
        if ($this->permissionTablesReady()) {
            $names = Role::query()->orderBy('name')->pluck('name')->all();
            if (!empty($names)) {
                return $names;
            }
        }

        return ['super-admin', 'admin', 'manager', 'user', 'test-role'];
    }

    /**
     * @return array<string>
     */
    private function availablePermissionNames(): array
    {
        if (!$this->permissionTablesReady()) {
            return [];
        }

        return Permission::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * @return array<string, array{label:string, description:string, view_permission:string, manage_permission:string}>
     */
    private function accessModules(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'description' => 'Route and widgets for /dashboard.',
                'view_permission' => 'view dashboard',
                'manage_permission' => 'manage dashboard',
            ],
            'accounts' => [
                'label' => 'Accounts',
                'description' => 'Bank and wallet balance management.',
                'view_permission' => 'view accounts',
                'manage_permission' => 'manage accounts',
            ],
            'transactions' => [
                'label' => 'Transactions',
                'description' => 'Expense and income CRUD routes.',
                'view_permission' => 'view transactions',
                'manage_permission' => 'manage transactions',
            ],
            'portfolio' => [
                'label' => 'Portfolio',
                'description' => 'IPO tracker and gold holdings.',
                'view_permission' => 'view portfolio',
                'manage_permission' => 'manage portfolio',
            ],
            'suggestions' => [
                'label' => 'Suggestions',
                'description' => 'ML and rule-based recommendation feed.',
                'view_permission' => 'view suggestions',
                'manage_permission' => 'manage suggestions',
            ],
            'docs' => [
                'label' => 'Docs',
                'description' => 'Starter kit docs and implementation guidance.',
                'view_permission' => 'view docs',
                'manage_permission' => 'manage docs',
            ],
            'settings' => [
                'label' => 'Settings',
                'description' => 'System settings screen and env editor.',
                'view_permission' => 'view settings',
                'manage_permission' => 'manage settings',
            ],
            'users' => [
                'label' => 'Users & Roles',
                'description' => 'Role assignment and user access updates.',
                'view_permission' => 'view users',
                'manage_permission' => 'manage users',
            ],
            'notifications' => [
                'label' => 'Notifications',
                'description' => 'In-app feed and broadcast actions.',
                'view_permission' => 'view notifications',
                'manage_permission' => 'manage notifications',
            ],
            'integrations' => [
                'label' => 'Integrations',
                'description' => 'Facebook, Telegram, and external connectors.',
                'view_permission' => 'view integrations',
                'manage_permission' => 'manage integrations',
            ],
            'ml' => [
                'label' => 'ML Tools',
                'description' => 'ML suggestions and model controls.',
                'view_permission' => 'view ml',
                'manage_permission' => 'manage ml',
            ],
            'exports' => [
                'label' => 'Export/Import',
                'description' => 'Excel and data transfer actions.',
                'view_permission' => 'view exports',
                'manage_permission' => 'manage exports',
            ],
        ];
    }

    /**
     * @param array<string, array{label:string, description:string, view_permission:string, manage_permission:string}> $modules
     * @return array<string>
     */
    private function modulePermissionNames(array $modules): array
    {
        $permissions = [];

        foreach ($modules as $module) {
            $permissions[] = $module['view_permission'];
            $permissions[] = $module['manage_permission'];
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @param \Illuminate\Support\Collection<int, Role> $roles
     * @param array<string, array{label:string, description:string, view_permission:string, manage_permission:string}> $modules
     * @return array<string, array<string, string>>
     */
    private function buildRoleAccessMap($roles, array $modules): array
    {
        $map = [];

        foreach ($roles as $role) {
            $permissions = $role->permissions->pluck('name')->all();

            foreach ($modules as $moduleKey => $meta) {
                if (in_array($meta['manage_permission'], $permissions, true)) {
                    $map[$role->name][$moduleKey] = 'manage';
                } elseif (in_array($meta['view_permission'], $permissions, true)) {
                    $map[$role->name][$moduleKey] = 'view';
                } else {
                    $map[$role->name][$moduleKey] = 'none';
                }
            }
        }

        return $map;
    }

    /**
     * @param \Illuminate\Support\Collection<int, Role> $roles
     * @param array<string> $modulePermissionNames
     * @return array<string, array<string>>
     */
    private function buildRoleExtraPermissionMap($roles, array $modulePermissionNames): array
    {
        $map = [];

        foreach ($roles as $role) {
            $map[$role->name] = $role->permissions
                ->pluck('name')
                ->filter(fn (string $permission) => !in_array($permission, $modulePermissionNames, true))
                ->values()
                ->all();
        }

        return $map;
    }

    /**
     * @param array<int, string> $selected
     * @param array<int, string> $allowed
     * @return array<int, string>
     */
    private function normalizedPermissions(array $selected, array $allowed): array
    {
        $allowedSet = array_fill_keys($allowed, true);

        return array_values(array_filter(array_map(
            fn ($permission) => trim((string) $permission),
            $selected
        ), fn (string $permission) => $permission !== '' && isset($allowedSet[$permission])));
    }

    /**
     * @param array<string, mixed> $moduleAccess
     * @return array<int, string>
     */
    private function moduleAccessPermissions(array $moduleAccess): array
    {
        $modules = $this->accessModules();
        $permissions = [];

        foreach ($modules as $moduleKey => $module) {
            $level = strtolower(trim((string) ($moduleAccess[$moduleKey] ?? 'none')));
            if (!in_array($level, ['none', 'view', 'manage'], true)) {
                $level = 'none';
            }

            if ($level === 'view' || $level === 'manage') {
                $permissions[] = (string) $module['view_permission'];
            }

            if ($level === 'manage') {
                $permissions[] = (string) $module['manage_permission'];
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @return array<int, string>
     */
    private function protectedRoleNames(): array
    {
        return ['super-admin', 'admin'];
    }

    /**
     * @return array<int, int>
     */
    private function roleUserCounts(): array
    {
        if (!$this->permissionTablesReady()) {
            return [];
        }

        $tables = config('permission.table_names', []);
        $pivotTable = $tables['model_has_roles'] ?? '';
        if ($pivotTable === '' || !Schema::hasTable($pivotTable)) {
            return [];
        }

        return DB::table($pivotTable)
            ->select('role_id', DB::raw('COUNT(*) AS total'))
            ->where('model_type', User::class)
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function permissionTablesReady(): bool
    {
        $tables = config('permission.table_names', []);

        if (!is_array($tables) || empty($tables)) {
            return false;
        }

        $requiredTableKeys = [
            'permissions',
            'roles',
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
        ];

        foreach ($requiredTableKeys as $tableKey) {
            if (empty($tables[$tableKey])) {
                return false;
            }
        }

        return Schema::hasTable($tables['permissions'])
            && Schema::hasTable($tables['roles'])
            && Schema::hasTable($tables['model_has_permissions'])
            && Schema::hasTable($tables['model_has_roles'])
            && Schema::hasTable($tables['role_has_permissions']);
    }

    private function assertCan(Request $request, string $permission, string $message): void
    {
        $user = $request->user();

        if (!$user || !$user->can($permission)) {
            abort(403, $message);
        }
    }

    /**
     * @param array<string, string> $values
     */
    private function opsUiEnabled(array $values = []): bool
    {
        if (array_key_exists('HAARRAY_ALLOW_SHELL_UI', $values)) {
            return filter_var((string) $values['HAARRAY_ALLOW_SHELL_UI'], FILTER_VALIDATE_BOOL);
        }

        return (bool) config('haarray.ops.allow_shell_ui', false);
    }

    /**
     * @return array{connection:string,driver:string,host:string,port:string,database:string,username:string}
     */
    private function databaseConnectionInfo(): array
    {
        $connection = (string) config('database.default', 'mysql');
        $config = (array) config('database.connections.' . $connection, []);

        $database = (string) ($config['database'] ?? '');
        try {
            $runtimeName = (string) DB::connection($connection)->getDatabaseName();
            if ($runtimeName !== '') {
                $database = $runtimeName;
            }
        } catch (Throwable $exception) {
            // Keep config-level fallback when runtime lookup fails.
        }

        return [
            'connection' => $connection,
            'driver' => (string) ($config['driver'] ?? ''),
            'host' => (string) ($config['host'] ?? ''),
            'port' => (string) ($config['port'] ?? ''),
            'database' => $database,
            'username' => (string) ($config['username'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOpsSnapshot(string $connection): array
    {
        return [
            'php_version' => PHP_VERSION,
            'app_env' => app()->environment(),
            'app_debug' => config('app.debug') ? 'true' : 'false',
            'db_tables' => $this->databaseTableSnapshot($connection),
            'log_tail' => $this->tailFile(storage_path('logs/laravel.log'), 140),
        ];
    }

    /**
     * @return array<int, array{name:string,row_count:int|null,columns:string}>
     */
    private function databaseTableSnapshot(string $connection): array
    {
        $tables = [];
        try {
            $listing = Schema::connection($connection)->getTableListing();
        } catch (Throwable $exception) {
            return [];
        }
        sort($listing);

        foreach ($listing as $tableName) {
            $rowCount = null;
            $columns = [];

            try {
                $rowCount = (int) DB::connection($connection)->table($tableName)->count();
            } catch (Throwable $exception) {
                $rowCount = null;
            }

            try {
                $columns = Schema::connection($connection)->getColumnListing($tableName);
            } catch (Throwable $exception) {
                $columns = [];
            }

            $previewColumns = array_slice($columns, 0, 8);
            $columnLabel = implode(', ', $previewColumns);
            if (count($columns) > 8) {
                $columnLabel .= ' ...';
            }

            $tables[] = [
                'name' => $tableName,
                'row_count' => $rowCount,
                'columns' => $columnLabel,
            ];
        }

        return $tables;
    }

    /**
     * @return array{tables:array<int, string>,selected:string,columns:array<int,string>,rows:array<int,array<string,mixed>>,row_count:int|null,connection:string,error:string}
     */
    private function buildDbBrowser(string $selectedTable = '', string $connection = ''): array
    {
        $targetConnection = trim($connection) !== '' ? $connection : (string) config('database.default', 'mysql');

        try {
            $tables = Schema::connection($targetConnection)->getTableListing();
        } catch (Throwable $exception) {
            return [
                'tables' => [],
                'selected' => '',
                'columns' => [],
                'rows' => [],
                'row_count' => null,
                'connection' => $targetConnection,
                'error' => $exception->getMessage(),
            ];
        }

        sort($tables);
        $selected = in_array($selectedTable, $tables, true)
            ? $selectedTable
            : ((string) ($tables[0] ?? ''));

        if ($selected === '') {
            return [
                'tables' => [],
                'selected' => '',
                'columns' => [],
                'rows' => [],
                'row_count' => 0,
                'connection' => $targetConnection,
                'error' => '',
            ];
        }

        $columns = [];
        $rows = [];
        $rowCount = null;
        $error = '';

        try {
            $columns = Schema::connection($targetConnection)->getColumnListing($selected);
            $rowCount = (int) DB::connection($targetConnection)->table($selected)->count();
            $rows = DB::connection($targetConnection)->table($selected)
                ->limit(50)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        return [
            'tables' => $tables,
            'selected' => $selected,
            'columns' => $columns,
            'rows' => $rows,
            'row_count' => $rowCount,
            'connection' => $targetConnection,
            'error' => $error,
        ];
    }

    private function recentActivities(int $limit = 120)
    {
        if (!Schema::hasTable('user_activities')) {
            return collect();
        }

        return UserActivity::query()
            ->with(['user:id,name,email'])
            ->latest('id')
            ->limit(max(20, min($limit, 300)))
            ->get();
    }

    /**
     * @return array{ok:bool, exit_code:int, output:string}
     */
    private function runShell(string $command, int $timeoutSeconds = 30): array
    {
        try {
            $process = Process::fromShellCommandline($command, base_path());
            $process->setTimeout($timeoutSeconds);
            $process->run();

            $output = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());
            if ($errorOutput !== '') {
                $output = trim($output . PHP_EOL . $errorOutput);
            }

            return [
                'ok' => $process->isSuccessful(),
                'exit_code' => (int) $process->getExitCode(),
                'output' => $output !== '' ? $output : '(no output)',
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'exit_code' => 1,
                'output' => $exception->getMessage(),
            ];
        }
    }

    private function tailFile(string $path, int $lines = 120): string
    {
        if (!File::exists($path)) {
            return 'Log file not found.';
        }

        $allLines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($allLines)) {
            return 'Unable to read log file.';
        }

        $tail = array_slice($allLines, -1 * max(20, $lines));
        return trim(implode(PHP_EOL, $tail));
    }

    /**
     * @return array{ok:bool, exit_code:int, output:string}
     */
    private function clearApplicationLogs(): array
    {
        $logDirectory = storage_path('logs');

        try {
            File::ensureDirectoryExists($logDirectory, 0775, true);
            $logFiles = File::files($logDirectory);
            $cleared = 0;

            foreach ($logFiles as $file) {
                if (!str_ends_with((string) $file->getFilename(), '.log')) {
                    continue;
                }
                File::put((string) $file->getPathname(), '');
                $cleared++;
            }

            if ($cleared === 0) {
                File::put(storage_path('logs/laravel.log'), '');
                $cleared = 1;
            }

            return [
                'ok' => true,
                'exit_code' => 0,
                'output' => 'Cleared ' . $cleared . ' log file(s).',
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'exit_code' => 1,
                'output' => $exception->getMessage(),
            ];
        }
    }

    private function storeBrandAsset(UploadedFile $file, string $kind): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'png'));
        $safeExtension = preg_replace('/[^a-z0-9]/i', '', $extension) ?: 'png';
        $filename = 'ui-' . $kind . '-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(8)) . '.' . $safeExtension;

        $directory = public_path('uploads/branding');
        File::ensureDirectoryExists($directory, 0775, true);
        $file->move($directory, $filename);

        return 'uploads/branding/' . $filename;
    }

    /**
     * @return array<int, array{name:string,url:string,size_kb:string,modified_at:string}>
     */
    private function brandingMediaLibrary(int $limit = 60): array
    {
        $directory = public_path('uploads');
        if (!File::isDirectory($directory)) {
            return [];
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'ico', 'gif', 'mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'];
        $files = collect(File::allFiles($directory))
            ->filter(function ($file) use ($allowedExtensions) {
                return in_array(strtolower((string) $file->getExtension()), $allowedExtensions, true);
            })
            ->sortByDesc(fn ($file) => (int) $file->getMTime())
            ->take(max(1, min($limit, 300)));

        return $files->map(function ($file) {
            $filename = (string) $file->getFilename();
            $absolutePath = (string) $file->getPathname();
            $relative = str_replace('\\', '/', ltrim(str_replace(public_path(), '', $absolutePath), '/'));
            $extension = strtolower((string) $file->getExtension());
            $type = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'svg', 'ico', 'gif'], true) ? 'image' : 'audio';

            return [
                'name' => $filename,
                'path' => $relative,
                'url' => url($relative),
                'type' => $type,
                'extension' => $extension,
                'size_kb' => number_format(((int) $file->getSize()) / 1024, 1),
                'modified_at' => date('Y-m-d H:i', (int) $file->getMTime()),
            ];
        })->values()->all();
    }

    private function normalizeUiAssetInput(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(https?:)?\/\//i', $value) !== 1) {
            return ltrim($value, '/');
        }

        $parsed = parse_url($value);
        $path = trim((string) ($parsed['path'] ?? ''));
        if ($path !== '' && str_contains($path, '/uploads/')) {
            return ltrim($path, '/');
        }

        return $value;
    }

    private function normalizeImportHeading(string $heading, int $index): string
    {
        $normalized = Str::of($heading)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        if ($normalized !== '') {
            return $normalized;
        }

        return 'col_' . $index;
    }

    private function parseImportBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMlDiagnostics(): array
    {
        /** @var AdvancedMLService $ml */
        $ml = app(AdvancedMLService::class);

        $foodWarning = (float) config('haarray.ml.food_budget_warning', 0.35);
        $savingsTarget = (float) config('haarray.ml.savings_rate_target', 0.30);
        $idleCashThreshold = (float) config('haarray.ml.idle_cash_threshold', 5000);

        $sampleProfile = $ml->classifySpendingProfile(
            $foodWarning,
            max(0.05, $foodWarning * 0.5),
            $savingsTarget,
        );

        $scenarioMap = [
            'Conservative plan' => [
                'food_ratio' => 0.16,
                'entertainment_ratio' => 0.08,
                'savings_rate' => 0.42,
            ],
            'Balanced plan' => [
                'food_ratio' => 0.27,
                'entertainment_ratio' => 0.14,
                'savings_rate' => 0.30,
            ],
            'Risky spend plan' => [
                'food_ratio' => 0.42,
                'entertainment_ratio' => 0.26,
                'savings_rate' => 0.11,
            ],
        ];

        $scenarioOutputs = [];
        foreach ($scenarioMap as $label => $input) {
            $scenarioOutputs[] = [
                'label' => $label,
                'input' => $input,
                'output' => $ml->classifySpendingProfile(
                    (float) $input['food_ratio'],
                    (float) $input['entertainment_ratio'],
                    (float) $input['savings_rate'],
                ),
            ];
        }

        $clusterSeed = [120, 180, 220, 450, 620, 890, 1200, 1450, 2500, 3200, 4500, 5600, 7200];
        $clusterSummary = $ml->clusterAmounts($clusterSeed, 3);

        $checks = [
            [
                'title' => 'PHP-ML package',
                'status' => class_exists(\Phpml\Classification\KNearestNeighbors::class),
                'note' => class_exists(\Phpml\Classification\KNearestNeighbors::class)
                    ? 'KNN class loaded.'
                    : 'Package missing. Run composer install.',
            ],
            [
                'title' => 'ML feature flag',
                'status' => (bool) config('haarray.enable_ml', true),
                'note' => (bool) config('haarray.enable_ml', true)
                    ? 'HAARRAY_ML is enabled.'
                    : 'HAARRAY_ML is disabled in environment settings.',
            ],
            [
                'title' => 'Threshold sanity',
                'status' => $foodWarning > 0 && $foodWarning < 1 && $savingsTarget > 0 && $savingsTarget < 1,
                'note' => 'Food warning and savings target must be between 0 and 1.',
            ],
        ];

        return [
            'phpml_loaded' => class_exists(\Phpml\Classification\KNearestNeighbors::class),
            'feature_enabled' => (bool) config('haarray.enable_ml', true),
            'checks' => $checks,
            'thresholds' => [
                'idle_cash_threshold' => $idleCashThreshold,
                'food_budget_warning' => $foodWarning,
                'savings_rate_target' => $savingsTarget,
            ],
            'scenario_outputs' => $scenarioOutputs,
            'cluster_seed' => $clusterSeed,
            'cluster_summary' => $clusterSummary,
            'sample_profile' => $sampleProfile,
            'probe_defaults' => [
                'food_ratio' => max(0.05, min(0.95, $foodWarning)),
                'entertainment_ratio' => max(0.05, min(0.95, round($foodWarning * 0.55, 2))),
                'savings_rate' => max(0.05, min(0.95, $savingsTarget)),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notificationAutomationRules(): array
    {
        $raw = trim((string) AppSettings::get('notifications.automation_rules_json', ''));
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            return is_array($decoded) ? array_values($decoded) : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private function notificationAutomationModelOptions(): array
    {
        return [
            'activity' => 'Request / CRUD Activity',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     */
    private function saveNotificationAutomationRules(array $rules): void
    {
        $normalized = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $id = trim((string) ($rule['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'active' => (bool) ($rule['active'] ?? false),
                'model_key' => trim((string) ($rule['model_key'] ?? 'activity')) ?: 'activity',
                'event_name' => trim((string) ($rule['event_name'] ?? 'activity.request')) ?: 'activity.request',
                'methods' => array_values(array_unique(array_filter(array_map(
                    fn ($item) => strtoupper(trim((string) $item)),
                    (array) ($rule['methods'] ?? [])
                )))),
                'route_patterns' => array_values(array_filter(array_map(
                    fn ($item) => trim((string) $item),
                    (array) ($rule['route_patterns'] ?? [])
                ))),
                'title_template' => trim((string) ($rule['title_template'] ?? '')),
                'message_template' => trim((string) ($rule['message_template'] ?? '')),
                'level' => in_array((string) ($rule['level'] ?? 'info'), ['info', 'success', 'warning', 'error'], true)
                    ? (string) $rule['level']
                    : 'info',
                'channels' => array_values(array_unique(array_filter(array_map(
                    fn ($item) => trim((string) $item),
                    (array) ($rule['channels'] ?? ['in_app'])
                ), fn ($item) => in_array($item, ['in_app', 'telegram'], true)))),
                'throttle_seconds' => max(10, min((int) ($rule['throttle_seconds'] ?? 60), 3600)),
                'audience' => [
                    'type' => in_array((string) data_get($rule, 'audience.type', 'admins'), ['admins', 'all', 'role', 'users'], true)
                        ? (string) data_get($rule, 'audience.type')
                        : 'admins',
                    'role' => trim((string) data_get($rule, 'audience.role', '')),
                    'user_ids' => array_values(array_map('intval', (array) data_get($rule, 'audience.user_ids', []))),
                ],
            ];
        }

        AppSettings::putMany([
            'notifications.automation_rules_json' => json_encode($normalized, JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @param array<int, string> $allowedRoles
     */
    private function normalizeRoleForEmail(string $email, string $roleName, array $allowedRoles): string
    {
        $roleName = trim($roleName);
        $email = strtolower(trim($email));

        if ($roleName !== 'super-admin') {
            return $roleName;
        }

        $rootEmail = strtolower(trim((string) config('haarray.root_admin_email', 'prateekbhujelpb@gmail.com')));
        if ($rootEmail !== '' && $email === $rootEmail) {
            return 'super-admin';
        }

        if (in_array('admin', $allowedRoles, true)) {
            return 'admin';
        }

        if (in_array('user', $allowedRoles, true)) {
            return 'user';
        }

        return (string) ($allowedRoles[0] ?? 'user');
    }
}
