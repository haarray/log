<?php

namespace App\Http\Controllers;

use App\Models\UserActivity;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\HealthCheckService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class UiOptionsController extends Controller
{
    public function setLocale(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:en,ne'],
        ]);

        $locale = strtolower((string) $validated['locale']);
        $request->session()->put('haarray.locale', $locale);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'locale' => $locale,
            ]);
        }

        $message = $locale === 'ne'
            ? 'नेपाली भाषा मोड सक्रिय भयो।'
            : 'English language mode enabled.';

        return back()->with('success', $message);
    }

    public function leads(Request $request): JsonResponse
    {
        $search = trim((string) $request->get('q', ''));

        $users = User::query()
            ->select(['id', 'name', 'email'])
            ->when($search !== '', function ($query) use ($search) {
                $query
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        $results = $users->map(function (User $user) {
            return [
                'id'       => (string) $user->id,
                'text'     => $user->name,
                'subtitle' => $user->email,
                'image'    => $this->avatarFor($user->name),
            ];
        })->values();

        return response()->json([
            'results'    => $results,
            'pagination' => ['more' => false],
        ]);
    }

    private function avatarFor(string $name): string
    {
        return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=2f7df6&color=ffffff&size=64';
    }

    public function usersTable(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['roles:id,name'])
            ->select([
                'id',
                'name',
                'email',
                'role',
                'receive_in_app_notifications',
                'receive_telegram_notifications',
                'browser_notifications_enabled',
                'created_at',
            ]);

        return DataTables::eloquent($query)
            ->editColumn('role', function (User $user) {
                $roleName = optional($user->roles->first())->name ?: ($user->role ?: 'user');
                return strtoupper((string) $roleName);
            })
            ->addColumn('channels', function (User $user) {
                $channels = [];

                if ($user->receive_in_app_notifications) {
                    $channels[] = 'In-app';
                }

                if ($user->receive_telegram_notifications) {
                    $channels[] = 'Telegram';
                }

                if ($user->browser_notifications_enabled) {
                    $channels[] = 'Browser';
                }

                return !empty($channels) ? implode(', ', $channels) : 'None';
            })
            ->editColumn('created_at', fn (User $user) => optional($user->created_at)?->toIso8601String() ?: '')
            ->addColumn('actions', function (User $user) use ($request) {
                if (!$request->user() || !$request->user()->can('manage users')) {
                    return '<span class="h-muted">View only</span>';
                }

                $editButton = '<button type="button" class="btn btn-outline-secondary btn-sm h-action-icon" data-user-edit-id="' . (int) $user->id . '" title="Edit user" aria-label="Edit user">'
                    . '<i class="fa-solid fa-pen-to-square"></i>'
                    . '</button>';
                $accessButton = '<button type="button" class="btn btn-outline-secondary btn-sm h-action-icon" data-user-access-id="' . (int) $user->id . '" title="Role and permission access" aria-label="Role and permission access">'
                    . '<i class="fa-solid fa-user-shield"></i>'
                    . '</button>';
                $notifyButton = '<button type="button" class="btn btn-outline-secondary btn-sm h-action-icon" data-user-notify-id="' . (int) $user->id . '" title="Notification channels" aria-label="Notification channels">'
                    . '<i class="fa-solid fa-bell"></i>'
                    . '</button>';
                if ((int) $request->user()->id === (int) $user->id) {
                    return '<span class="h-action-group">' . $editButton . $accessButton . $notifyButton . '</span>';
                }

                $deleteAction = route('settings.users.delete', $user);
                $csrf = csrf_token();
                $deleteForm = '<form method="POST" action="' . e($deleteAction) . '" class="d-inline-block" data-spa data-confirm="true" data-confirm-title="Delete user?" data-confirm-text="This user account will be removed permanently.">'
                    . '<input type="hidden" name="_token" value="' . e($csrf) . '">'
                    . '<input type="hidden" name="_method" value="DELETE">'
                    . '<button type="submit" class="btn btn-outline-danger btn-sm h-action-icon" title="Delete user" aria-label="Delete user"><i class="fa-solid fa-trash"></i></button>'
                    . '</form>';

                return '<span class="h-action-group">' . $editButton . $accessButton . $notifyButton . $deleteForm . '</span>';
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function rolesTable(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderBy('id', 'desc')
            ->get();

        $roleUserCounts = $this->roleUserCounts();
        $protected = ['super-admin', 'admin'];

        $rows = $roles->map(function (Role $role) use ($roleUserCounts, $protected, $request) {
            $isProtected = in_array((string) $role->name, $protected, true);
            $assignedUsers = (int) ($roleUserCounts[$role->id] ?? 0);

            $actions = '<span class="h-muted">View only</span>';
            if ($request->user() && $request->user()->can('manage settings')) {
                $editButton = '<a href="' . e(route('settings.rbac.edit', $role)) . '" data-spa class="btn btn-outline-secondary btn-sm h-action-icon" title="Edit role" aria-label="Edit role"><i class="fa-solid fa-pen-to-square"></i></a>';

                $deleteDisabled = $isProtected || $assignedUsers > 0;
                $deleteTitle = $deleteDisabled
                    ? ($isProtected ? 'Protected role cannot be deleted' : 'Role is assigned to users')
                    : 'Delete role';

                $deleteAction = route('settings.roles.delete', $role);
                $csrf = csrf_token();
                $deleteForm = '<form method="POST" action="' . e($deleteAction) . '" class="d-inline-block" data-spa data-confirm="true" data-confirm-title="Delete role?" data-confirm-text="Role will be removed permanently if no user is assigned.">'
                    . '<input type="hidden" name="_token" value="' . e($csrf) . '">'
                    . '<input type="hidden" name="_method" value="DELETE">'
                    . '<button type="submit" class="btn btn-outline-danger btn-sm h-action-icon" title="' . e($deleteTitle) . '" aria-label="Delete role"' . ($deleteDisabled ? ' disabled' : '') . '><i class="fa-solid fa-trash"></i></button>'
                    . '</form>';

                $actions = '<span class="h-action-group">' . $editButton . $deleteForm . '</span>';
            }

            return [
                'id' => $role->id,
                'name' => strtoupper((string) $role->name),
                'permissions_count' => $role->permissions->count(),
                'users_count' => $assignedUsers,
                'is_protected' => $isProtected ? 'Yes' : 'No',
                'actions' => $actions,
            ];
        })->values();

        return DataTables::collection($rows)
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function activityTable(Request $request): JsonResponse
    {
        $query = UserActivity::query()
            ->with(['user:id,name,email'])
            ->select([
                'id',
                'user_id',
                'method',
                'path',
                'route_name',
                'ip_address',
                'meta',
                'created_at',
            ]);

        return DataTables::eloquent($query)
            ->addColumn('user', function (UserActivity $activity) {
                $name = (string) ($activity->user->name ?? 'Unknown');
                $email = (string) ($activity->user->email ?? '');

                return $email !== '' ? $name . ' (' . $email . ')' : $name;
            })
            ->editColumn('method', fn (UserActivity $activity) => strtoupper((string) $activity->method))
            ->addColumn('status', function (UserActivity $activity) {
                return (string) data_get($activity->meta, 'status', '-');
            })
            ->editColumn('created_at', fn (UserActivity $activity) => optional($activity->created_at)?->toIso8601String() ?: '')
            ->toJson();
    }

    public function healthReport(Request $request, HealthCheckService $health): JsonResponse
    {
        if (!$request->user() || !$request->user()->can('view settings')) {
            abort(403, 'You do not have permission to view health diagnostics.');
        }

        return response()->json($health->report());
    }

    public function hotReloadSignature(Request $request): JsonResponse
    {
        if (!app()->environment('local') || !(bool) config('haarray.ops.hot_reload', false)) {
            abort(404);
        }

        $signature = $this->computeHotReloadSignature();
        $current = trim((string) $request->query('sig', ''));

        if ($current !== '' && hash_equals($current, $signature)) {
            return response()->json([], 204);
        }

        return response()->json([
            'signature' => $signature,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    public function hotReloadStream(Request $request): StreamedResponse
    {
        if (!app()->environment('local') || !(bool) config('haarray.ops.hot_reload', false)) {
            abort(404);
        }

        $initialSignature = trim((string) $request->query('sig', ''));
        $maxSeconds = max(15, min((int) config('haarray.ops.hot_reload_stream_max_seconds', 55), 240));
        $sleepMs = max(300, min((int) config('haarray.ops.hot_reload_stream_sleep_ms', 1000), 5000));

        return response()->stream(function () use ($initialSignature, $maxSeconds, $sleepMs): void {
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            $lastSignature = $initialSignature;
            $startedAt = microtime(true);
            $sentAnySignature = false;

            while (!connection_aborted() && (microtime(true) - $startedAt) < $maxSeconds) {
                $signature = $this->computeHotReloadSignature();

                if ($signature !== '' && ($signature !== $lastSignature || !$sentAnySignature)) {
                    $payload = [
                        'signature' => $signature,
                        'generated_at' => now()->toDateTimeString(),
                        'initial' => !$sentAnySignature,
                    ];

                    echo "event: signature\n";
                    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n\n";

                    $lastSignature = $signature;
                    $sentAnySignature = true;
                } else {
                    echo ": keepalive\n\n";
                }

                @flush();
                usleep($sleepMs * 1000);
            }

            echo "event: end\n";
            echo "data: {}\n\n";
            @flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function fileManagerList(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->can('view settings')) {
            abort(403, 'You do not have permission to view media files.');
        }

        $query = trim((string) $request->query('q', ''));
        $limit = max(20, min((int) $request->query('limit', 80), 300));
        $folder = $this->sanitizeMediaFolder((string) $request->query('folder', ''));
        $baseRelative = $this->mediaBaseRelativePath($folder);
        $storage = $this->mediaStorageTarget();
        $allowedExtensions = $this->allowedMediaExtensions();

        $items = [];
        $folders = [];

        if ($storage['mode'] === 'disk') {
            $disk = Storage::disk((string) $storage['disk']);

            try {
                $files = collect($disk->files($baseRelative, false))
                    ->filter(function (string $path) use ($allowedExtensions, $query) {
                        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                        if (!in_array($extension, $allowedExtensions, true)) {
                            return false;
                        }
                        if ($query === '') {
                            return true;
                        }
                        return str_contains(strtolower((string) basename($path)), strtolower($query));
                    })
                    ->map(function (string $path) use ($disk, $storage) {
                        return $this->buildDiskMediaItem($disk, $path, (string) $storage['disk']);
                    })
                    ->filter()
                    ->sortByDesc(fn (array $item) => (string) ($item['modified_at'] ?? ''))
                    ->take($limit)
                    ->values()
                    ->all();

                $items = $files;

                $folders = collect($disk->directories($baseRelative))
                    ->map(function (string $path) {
                        $cleanPath = trim((string) preg_replace('#^uploads/?#', '', str_replace('\\', '/', $path)), '/');
                        return [
                            'name' => basename($cleanPath),
                            'path' => $cleanPath,
                        ];
                    })
                    ->sortBy('name')
                    ->values()
                    ->all();
            } catch (Throwable) {
                $items = [];
                $folders = [];
            }
        } else {
            $directory = public_path($baseRelative);
            if (File::isDirectory($directory)) {
                $items = collect(File::files($directory))
                    ->filter(function ($file) use ($allowedExtensions, $query) {
                        $extension = strtolower((string) $file->getExtension());
                        if (!in_array($extension, $allowedExtensions, true)) {
                            return false;
                        }
                        if ($query === '') {
                            return true;
                        }
                        return str_contains(strtolower((string) $file->getFilename()), strtolower($query));
                    })
                    ->map(function ($file) {
                        $absolutePath = (string) $file->getPathname();
                        $relative = str_replace('\\', '/', ltrim(str_replace(public_path(), '', $absolutePath), '/'));
                        return $this->buildLocalMediaItem($relative);
                    })
                    ->filter()
                    ->sortByDesc(fn (array $item) => (string) ($item['modified_at'] ?? ''))
                    ->take($limit)
                    ->values()
                    ->all();

                $folders = collect(File::directories($directory))
                    ->map(function (string $absoluteDirectory) {
                        $relative = str_replace('\\', '/', ltrim(str_replace(public_path('uploads'), '', $absoluteDirectory), '/'));
                        return [
                            'name' => basename($absoluteDirectory),
                            'path' => trim($relative, '/'),
                        ];
                    })
                    ->sortBy('name')
                    ->values()
                    ->all();
            }
        }

        return response()->json([
            'items' => $items,
            'folders' => $folders,
            'current_folder' => $folder,
            'storage' => [
                'mode' => $storage['mode'],
                'disk' => $storage['disk'],
                'label' => $storage['label'],
            ],
        ]);
    }

    public function fileManagerDelete(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->can('manage settings')) {
            abort(403, 'You do not have permission to delete media files.');
        }

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $relativePath = $this->normalizeMediaPath((string) $validated['path']);
        if (!str_starts_with($relativePath, 'uploads/')) {
            return response()->json([
                'ok' => false,
                'message' => 'Only files inside uploads/ can be deleted.',
            ], 422);
        }

        $storage = $this->mediaStorageTarget();
        $absoluteUrl = '';

        if ($storage['mode'] === 'disk') {
            $disk = Storage::disk((string) $storage['disk']);
            if (!$disk->exists($relativePath)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Media file not found.',
                ], 404);
            }

            $absoluteUrl = $this->mediaFileUrl($relativePath, $storage);
            $disk->delete($relativePath);
        } else {
            $absolutePath = public_path($relativePath);
            $realPublic = realpath(public_path()) ?: public_path();
            $realFile = realpath($absolutePath);

            if ($realFile === false || !str_starts_with($realFile, $realPublic) || !File::exists($realFile)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Media file not found.',
                ], 404);
            }

            $absoluteUrl = $this->mediaFileUrl($relativePath, $storage);
            File::delete($realFile);
        }

        $this->clearBrandingAssetReferences($relativePath, $absoluteUrl);

        return response()->json([
            'ok' => true,
            'message' => 'Media file deleted.',
        ]);
    }

    public function fileManagerUpload(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->can('manage settings')) {
            abort(403, 'You do not have permission to upload media files.');
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg,ico,mp3,wav,ogg,m4a,aac,flac', 'max:15360'],
            'folder' => ['nullable', 'string', 'max:180'],
        ]);

        $folder = $this->sanitizeMediaFolder((string) ($validated['folder'] ?? 'library'));
        $targetPath = $this->mediaBaseRelativePath($folder) . '/' . now()->format('Y/m');
        $file = $validated['file'];
        if (!$file instanceof UploadedFile) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid upload payload.',
            ], 422);
        }

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $safeExtension = preg_replace('/[^a-z0-9]/i', '', $extension) ?: 'png';
        $filename = now()->format('YmdHis') . '-' . strtolower(str()->random(8)) . '.' . $safeExtension;
        $relative = trim($targetPath . '/' . $filename, '/');

        $storage = $this->mediaStorageTarget();
        if ($storage['mode'] === 'disk') {
            $disk = Storage::disk((string) $storage['disk']);
            $disk->putFileAs($targetPath, $file, $filename, ['visibility' => 'public']);
            $item = $this->buildDiskMediaItem($disk, $relative, (string) $storage['disk']);
        } else {
            $targetDirectory = public_path($targetPath);
            File::ensureDirectoryExists($targetDirectory, 0775, true);
            $file->move($targetDirectory, $filename);
            $item = $this->buildLocalMediaItem($relative);
        }

        if ($item === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Upload succeeded but media metadata could not be generated.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'item' => $item,
        ]);
    }

    public function fileManagerCreateFolder(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->can('manage settings')) {
            abort(403, 'You do not have permission to create media folders.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'parent' => ['nullable', 'string', 'max:180'],
        ]);

        $parent = $this->sanitizeMediaFolder((string) ($validated['parent'] ?? ''));
        $name = $this->sanitizeMediaFolderSegment((string) $validated['name']);
        if ($name === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Folder name is invalid.',
            ], 422);
        }

        $relativeFolder = trim(($parent !== '' ? $parent . '/' : '') . $name, '/');
        $baseRelative = $this->mediaBaseRelativePath($relativeFolder);
        $storage = $this->mediaStorageTarget();

        if ($storage['mode'] === 'disk') {
            $disk = Storage::disk((string) $storage['disk']);
            $keepFile = trim($baseRelative . '/.keep', '/');
            $disk->put($keepFile, '', ['visibility' => 'public']);
        } else {
            File::ensureDirectoryExists(public_path($baseRelative), 0775, true);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Folder created successfully.',
            'folder' => [
                'name' => $name,
                'path' => $relativeFolder,
            ],
        ]);
    }

    public function fileManagerExportCsv(Request $request): StreamedResponse
    {
        if (!$request->user() || !$request->user()->can('view settings')) {
            abort(403, 'You do not have permission to export media files.');
        }

        $folder = $this->sanitizeMediaFolder((string) $request->query('folder', ''));
        $baseRelative = $this->mediaBaseRelativePath($folder);
        $storage = $this->mediaStorageTarget();
        $filename = 'haarray-media-' . now()->format('Ymd_His') . '.csv';
        $allowedExtensions = $this->allowedMediaExtensions();

        $headers = [
            'name',
            'path',
            'url',
            'type',
            'extension',
            'size_kb',
            'modified_at',
            'storage_mode',
            'storage_disk',
        ];

        return response()->streamDownload(function () use ($storage, $baseRelative, $headers, $allowedExtensions): void {
            $output = fopen('php://output', 'wb');
            if (!is_resource($output)) {
                return;
            }

            fputcsv($output, $headers);

            if ($storage['mode'] === 'disk') {
                $diskName = (string) $storage['disk'];
                $disk = Storage::disk($diskName);
                $paths = $disk->allFiles($baseRelative);
                foreach ($paths as $path) {
                    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                    if (!in_array($extension, $allowedExtensions, true)) {
                        continue;
                    }

                    $item = $this->buildDiskMediaItem($disk, $path, $diskName);
                    if ($item === null) {
                        continue;
                    }

                    fputcsv($output, [
                        $item['name'],
                        $item['path'],
                        $item['url'],
                        $item['type'],
                        $item['extension'],
                        $item['size_kb'],
                        $item['modified_at'],
                        $storage['mode'],
                        $storage['disk'],
                    ]);
                }
            } else {
                $directory = public_path($baseRelative);
                if (File::isDirectory($directory)) {
                    $files = File::allFiles($directory);
                    foreach ($files as $file) {
                        $extension = strtolower((string) $file->getExtension());
                        if (!in_array($extension, $allowedExtensions, true)) {
                            continue;
                        }

                        $relative = str_replace('\\', '/', ltrim(str_replace(public_path(), '', (string) $file->getPathname()), '/'));
                        $item = $this->buildLocalMediaItem($relative);
                        if ($item === null) {
                            continue;
                        }

                        fputcsv($output, [
                            $item['name'],
                            $item['path'],
                            $item['url'],
                            $item['type'],
                            $item['extension'],
                            $item['size_kb'],
                            $item['modified_at'],
                            $storage['mode'],
                            $storage['disk'],
                        ]);
                    }
                }
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function fileManagerResize(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->can('manage settings')) {
            abort(403, 'You do not have permission to resize media files.');
        }

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'width' => ['required', 'integer', 'between:32,4096'],
            'height' => ['required', 'integer', 'between:32,4096'],
            'replace' => ['nullable', 'boolean'],
        ]);

        $storage = $this->mediaStorageTarget();
        if ($storage['mode'] !== 'local') {
            return response()->json([
                'ok' => false,
                'message' => 'Image resize is available for local storage mode only.',
            ], 422);
        }

        $relativePath = $this->normalizeMediaPath((string) $validated['path']);
        if (!str_starts_with($relativePath, 'uploads/')) {
            return response()->json([
                'ok' => false,
                'message' => 'Only files inside uploads/ can be resized.',
            ], 422);
        }

        $absolutePath = public_path($relativePath);
        $realPublic = realpath(public_path()) ?: public_path();
        $realFile = realpath($absolutePath);
        if ($realFile === false || !str_starts_with($realFile, $realPublic) || !File::exists($realFile)) {
            return response()->json([
                'ok' => false,
                'message' => 'Image file not found.',
            ], 404);
        }

        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled') || !function_exists('getimagesize')) {
            return response()->json([
                'ok' => false,
                'message' => 'GD image extension is required for resize.',
            ], 422);
        }

        $extension = strtolower((string) pathinfo($realFile, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Only jpg, jpeg, png, webp, and gif images can be resized.',
            ], 422);
        }

        $sourceInfo = getimagesize($realFile);
        if (!is_array($sourceInfo) || ($sourceInfo[0] ?? 0) < 1 || ($sourceInfo[1] ?? 0) < 1) {
            return response()->json([
                'ok' => false,
                'message' => 'Unable to read image dimensions.',
            ], 422);
        }

        $sourceWidth = (int) $sourceInfo[0];
        $sourceHeight = (int) $sourceInfo[1];
        $targetWidth = (int) $validated['width'];
        $targetHeight = (int) $validated['height'];

        $scale = min($targetWidth / max(1, $sourceWidth), $targetHeight / max(1, $sourceHeight));
        $scale = min(1.0, max(0.01, $scale));
        $newWidth = max(1, (int) floor($sourceWidth * $scale));
        $newHeight = max(1, (int) floor($sourceHeight * $scale));

        $sourceImage = match ($extension) {
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($realFile) : false,
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($realFile) : false,
            'gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($realFile) : false,
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realFile) : false,
            default => false,
        };

        if (!is_resource($sourceImage) && !is_object($sourceImage)) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to open the source image.',
            ], 422);
        }

        $targetImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($targetImage === false) {
            if (is_resource($sourceImage) || is_object($sourceImage)) {
                imagedestroy($sourceImage);
            }
            return response()->json([
                'ok' => false,
                'message' => 'Failed to allocate destination image.',
            ], 500);
        }

        if (in_array($extension, ['png', 'gif', 'webp'], true)) {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
            imagefilledrectangle($targetImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);

        $replace = (bool) ($validated['replace'] ?? false);
        $outputRelative = $relativePath;
        if (!$replace) {
            $directory = trim((string) pathinfo($relativePath, PATHINFO_DIRNAME), '/');
            $basename = (string) pathinfo($relativePath, PATHINFO_FILENAME);
            $outputRelative = ($directory !== '' ? $directory . '/' : '') . $basename . '-' . $newWidth . 'x' . $newHeight . '.' . $extension;
        }
        $outputAbsolute = public_path($outputRelative);

        $saved = match ($extension) {
            'jpg', 'jpeg' => function_exists('imagejpeg') ? imagejpeg($targetImage, $outputAbsolute, 88) : false,
            'png' => function_exists('imagepng') ? imagepng($targetImage, $outputAbsolute, 6) : false,
            'gif' => function_exists('imagegif') ? imagegif($targetImage, $outputAbsolute) : false,
            'webp' => function_exists('imagewebp') ? imagewebp($targetImage, $outputAbsolute, 88) : false,
            default => false,
        };

        imagedestroy($sourceImage);
        imagedestroy($targetImage);

        if (!$saved) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to save resized image.',
            ], 500);
        }

        $item = $this->buildLocalMediaItem($outputRelative);
        if ($item === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Image resized, but resulting file metadata could not be read.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Image resized successfully.',
            'item' => $item,
        ]);
    }

    public function globalSearch(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['items' => []]);
        }

        $items = [];
        $maxItems = max(8, min((int) $request->query('limit', 20), 80));
        $perSource = max(3, min((int) $request->query('per_source', 8), 20));

        foreach ($this->globalSearchLinksConfig() as $link) {
            $permission = trim((string) ($link['permission'] ?? ''));
            if ($permission !== '' && !$user->can($permission)) {
                continue;
            }

            $title = trim((string) ($link['title'] ?? ''));
            $subtitle = trim((string) ($link['subtitle'] ?? ''));
            $haystack = strtolower($title . ' ' . $subtitle);
            if (!str_contains($haystack, strtolower($query))) {
                continue;
            }

            $url = $this->resolveSearchLinkUrl($link);
            if ($url === '') {
                continue;
            }

            $items[] = [
                'id' => 'link-' . sha1($url . $title),
                'title' => $title !== '' ? $title : 'Link',
                'subtitle' => $subtitle,
                'url' => $url,
                'icon' => (string) ($link['icon'] ?? 'fa-solid fa-link'),
                'type' => 'link',
            ];
            if (count($items) >= $maxItems) {
                return response()->json(['items' => array_slice($items, 0, $maxItems)]);
            }
        }

        foreach ($this->globalSearchModelsConfig() as $source) {
            $permission = trim((string) ($source['permission'] ?? ''));
            if ($permission !== '' && !$user->can($permission)) {
                continue;
            }

            $rows = $this->searchModelSource($source, $query, $perSource);
            foreach ($rows as $row) {
                $items[] = $row;
                if (count($items) >= $maxItems) {
                    break 2;
                }
            }
        }

        return response()->json([
            'items' => array_slice($items, 0, $maxItems),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function globalSearchLinksConfig(): array
    {
        $config = (array) config('haarray.global_search.links', []);
        return array_values(array_filter($config, fn ($item) => is_array($item)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function globalSearchModelsConfig(): array
    {
        $config = (array) config('haarray.global_search.models', []);
        $dynamic = trim(AppSettings::get('search.registry_json', ''));

        if ($dynamic !== '') {
            try {
                $decoded = json_decode($dynamic, true, flags: JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            } catch (Throwable) {
                // Keep static fallback when dynamic JSON is invalid.
            }
        }

        return array_values(array_filter($config, fn ($item) => is_array($item)));
    }

    /**
     * @param array<string, mixed> $source
     * @return array<int, array<string, string>>
     */
    private function searchModelSource(array $source, string $query, int $limit): array
    {
        $modelClass = trim((string) ($source['model'] ?? ''));
        if ($modelClass === '' || !class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        $searchFields = array_values(array_filter(array_map(
            fn ($field) => trim((string) $field),
            (array) ($source['search'] ?? [])
        )));
        if (empty($searchFields)) {
            return [];
        }

        $titleField = trim((string) ($source['title'] ?? $searchFields[0]));
        $subtitleField = trim((string) ($source['subtitle'] ?? ''));
        $idField = trim((string) ($source['id'] ?? 'id'));
        $icon = trim((string) ($source['icon'] ?? 'fa-solid fa-file-lines'));
        $type = trim((string) ($source['key'] ?? 'record'));

        /** @var class-string<Model> $modelClass */
        $model = new $modelClass();
        $table = (string) $model->getTable();
        if ($table === '') {
            return [];
        }

        try {
            $columns = Schema::getColumnListing($table);
        } catch (Throwable) {
            return [];
        }
        if (empty($columns)) {
            return [];
        }

        $columnSet = array_fill_keys($columns, true);
        $relationColumnSets = [];
        $resolveRelation = function (string $relationName) use ($model, &$relationColumnSets): ?array {
            $relationName = trim($relationName);
            if ($relationName === '') {
                return null;
            }
            if (array_key_exists($relationName, $relationColumnSets)) {
                return $relationColumnSets[$relationName];
            }
            if (!method_exists($model, $relationName)) {
                $relationColumnSets[$relationName] = null;
                return null;
            }

            try {
                $relation = $model->{$relationName}();
            } catch (Throwable) {
                $relationColumnSets[$relationName] = null;
                return null;
            }
            if (!$relation instanceof Relation) {
                $relationColumnSets[$relationName] = null;
                return null;
            }

            $related = $relation->getRelated();
            if (!$related instanceof Model) {
                $relationColumnSets[$relationName] = null;
                return null;
            }

            $relatedTable = trim((string) $related->getTable());
            if ($relatedTable === '') {
                $relationColumnSets[$relationName] = null;
                return null;
            }

            try {
                $relatedColumns = Schema::getColumnListing($relatedTable);
            } catch (Throwable) {
                $relationColumnSets[$relationName] = null;
                return null;
            }
            if (empty($relatedColumns)) {
                $relationColumnSets[$relationName] = null;
                return null;
            }

            $relationColumnSets[$relationName] = [
                'columns' => array_fill_keys($relatedColumns, true),
            ];

            return $relationColumnSets[$relationName];
        };

        $directSearchFields = [];
        $relationSearchMap = [];
        foreach ($searchFields as $field) {
            if (!str_contains($field, '.')) {
                if (isset($columnSet[$field])) {
                    $directSearchFields[] = $field;
                }
                continue;
            }

            [$relationName, $relationColumn] = array_pad(explode('.', $field, 2), 2, '');
            $relationName = trim($relationName);
            $relationColumn = trim($relationColumn);
            if ($relationName === '' || $relationColumn === '') {
                continue;
            }

            $relationMeta = $resolveRelation($relationName);
            if ($relationMeta === null || !isset($relationMeta['columns'][$relationColumn])) {
                continue;
            }

            if (!isset($relationSearchMap[$relationName])) {
                $relationSearchMap[$relationName] = [];
            }
            $relationSearchMap[$relationName][] = $relationColumn;
        }

        $directSearchFields = array_values(array_unique($directSearchFields));
        foreach ($relationSearchMap as $relationName => $columnsForRelation) {
            $relationSearchMap[$relationName] = array_values(array_unique($columnsForRelation));
        }

        if (empty($directSearchFields) && empty($relationSearchMap)) {
            return [];
        }

        $idField = isset($columnSet[$idField]) ? $idField : (string) $model->getKeyName();
        if ($idField === '' || !isset($columnSet[$idField])) {
            return [];
        }

        $titleUsesRelation = false;
        $titleRelationName = '';
        $titleRelationColumn = '';
        if (!isset($columnSet[$titleField]) && str_contains($titleField, '.')) {
            [$titleRelationName, $titleRelationColumn] = array_pad(explode('.', $titleField, 2), 2, '');
            $titleRelationName = trim($titleRelationName);
            $titleRelationColumn = trim($titleRelationColumn);
            $relationMeta = $resolveRelation($titleRelationName);
            $titleUsesRelation = $relationMeta !== null && isset($relationMeta['columns'][$titleRelationColumn]);
        }

        if (!isset($columnSet[$titleField]) && !$titleUsesRelation) {
            $titleField = $idField;
            $titleUsesRelation = false;
        }

        $subtitleUsesRelation = false;
        $subtitleRelationName = '';
        $subtitleRelationColumn = '';
        if ($subtitleField !== '' && !isset($columnSet[$subtitleField]) && str_contains($subtitleField, '.')) {
            [$subtitleRelationName, $subtitleRelationColumn] = array_pad(explode('.', $subtitleField, 2), 2, '');
            $subtitleRelationName = trim($subtitleRelationName);
            $subtitleRelationColumn = trim($subtitleRelationColumn);
            $relationMeta = $resolveRelation($subtitleRelationName);
            $subtitleUsesRelation = $relationMeta !== null && isset($relationMeta['columns'][$subtitleRelationColumn]);
        }

        if ($subtitleField !== '' && !isset($columnSet[$subtitleField]) && !$subtitleUsesRelation) {
            $subtitleField = '';
        }

        $queryBuilder = $modelClass::query();
        $queryBuilder->where(function ($builder) use ($directSearchFields, $relationSearchMap, $query) {
            $hasCondition = false;

            foreach ($directSearchFields as $field) {
                if (!$hasCondition) {
                    $builder->where($field, 'like', '%' . $query . '%');
                    $hasCondition = true;
                } else {
                    $builder->orWhere($field, 'like', '%' . $query . '%');
                }
            }

            foreach ($relationSearchMap as $relationName => $columnsForRelation) {
                $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                $builder->{$method}($relationName, function ($relationQuery) use ($columnsForRelation, $query) {
                    foreach ($columnsForRelation as $index => $column) {
                        if ($index === 0) {
                            $relationQuery->where($column, 'like', '%' . $query . '%');
                            continue;
                        }
                        $relationQuery->orWhere($column, 'like', '%' . $query . '%');
                    }
                });
                $hasCondition = true;
            }
        });

        $selectColumns = array_values(array_unique(array_filter([
            $idField,
            isset($columnSet[$titleField]) ? $titleField : null,
            $subtitleField !== '' && isset($columnSet[$subtitleField]) ? $subtitleField : null,
        ])));
        if (!empty($selectColumns)) {
            $queryBuilder->select($selectColumns);
        }

        $withRelations = [];
        if ($titleUsesRelation && $titleRelationName !== '') {
            $withRelations[] = $titleRelationName;
        }
        if ($subtitleUsesRelation && $subtitleRelationName !== '') {
            $withRelations[] = $subtitleRelationName;
        }
        if (!empty($withRelations)) {
            $queryBuilder->with(array_values(array_unique($withRelations)));
        }

        try {
            $rows = $queryBuilder->limit(max(1, min($limit, 30)))->get();
        } catch (Throwable) {
            return [];
        }

        return $rows->map(function (Model $row) use ($idField, $titleField, $subtitleField, $source, $icon, $type) {
            $title = trim((string) data_get($row, $titleField));
            $subtitle = $subtitleField !== '' ? trim((string) data_get($row, $subtitleField)) : '';
            $url = $this->resolveSearchRowUrl($source, $row);
            if ($url === '') {
                return null;
            }

            $id = (string) data_get($row, $idField);
            return [
                'id' => $type . '-' . $id,
                'title' => $title !== '' ? $title : ('Record #' . $id),
                'subtitle' => $subtitle,
                'url' => $url,
                'icon' => $icon,
                'type' => $type,
            ];
        })->filter()->values()->all();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveSearchLinkUrl(array $config): string
    {
        $routeName = trim((string) ($config['route'] ?? ''));
        $params = is_array($config['params'] ?? null) ? $config['params'] : [];
        if ($routeName !== '' && Route::has($routeName)) {
            return route($routeName, $params);
        }

        $url = trim((string) ($config['url'] ?? ''));
        if ($url === '') {
            return '';
        }

        return Str::startsWith($url, ['http://', 'https://', '/']) ? $url : ('/' . ltrim($url, '/'));
    }

    /**
     * @param array<string, mixed> $source
     */
    private function resolveSearchRowUrl(array $source, Model $row): string
    {
        $routeName = trim((string) ($source['route'] ?? ''));
        $params = is_array($source['route_params'] ?? null) ? $source['route_params'] : [];
        if ($routeName !== '' && Route::has($routeName)) {
            $built = [];
            foreach ($params as $key => $value) {
                $built[(string) $key] = $this->replaceTemplateTokens((string) $value, $row);
            }
            $url = route($routeName, $built);
        } else {
            $url = trim((string) ($source['url'] ?? ''));
            if ($url === '') {
                return '';
            }
            $url = $this->replaceTemplateTokens($url, $row);
            if (!Str::startsWith($url, ['http://', 'https://', '/'])) {
                $url = '/' . ltrim($url, '/');
            }
        }

        $queryTemplate = trim((string) ($source['query'] ?? ''));
        if ($queryTemplate !== '') {
            $query = $this->replaceTemplateTokens($queryTemplate, $row);
            $url .= (str_contains($url, '?') ? '&' : '?') . ltrim($query, '?&');
        }

        return $url;
    }

    private function replaceTemplateTokens(string $value, Model $row): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use ($row) {
            $key = (string) ($matches[1] ?? '');
            if ($key === '') {
                return '';
            }
            return (string) data_get($row, $key, '');
        }, $value) ?? $value;
    }

    /**
     * @return array<int, string>
     */
    private function allowedMediaExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico', 'mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'];
    }

    /**
     * @return array{mode:string,disk:string,label:string}
     */
    private function mediaStorageTarget(): array
    {
        $defaultDisk = trim((string) config('filesystems.default', 'local'));
        $s3Bucket = trim((string) config('filesystems.disks.s3.bucket', ''));
        $s3Driver = trim((string) config('filesystems.disks.s3.driver', ''));

        if ($defaultDisk === 's3' && $s3Driver === 's3' && $s3Bucket !== '') {
            return [
                'mode' => 'disk',
                'disk' => 's3',
                'label' => 'AWS S3: ' . $s3Bucket,
            ];
        }

        return [
            'mode' => 'local',
            'disk' => 'public',
            'label' => 'Local /public/uploads',
        ];
    }

    private function sanitizeMediaFolder(string $folder): string
    {
        $folder = trim(str_replace('\\', '/', $folder), '/');
        if ($folder === '') {
            return '';
        }

        $segments = array_values(array_filter(array_map(function (string $segment) {
            return $this->sanitizeMediaFolderSegment($segment);
        }, explode('/', $folder))));

        return implode('/', $segments);
    }

    private function sanitizeMediaFolderSegment(string $segment): string
    {
        $segment = trim(str_replace('\\', '/', $segment));
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return '';
        }

        $clean = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $segment) ?? '';
        return trim($clean, '-.');
    }

    private function mediaBaseRelativePath(string $folder = ''): string
    {
        $folder = $this->sanitizeMediaFolder($folder);
        return $folder === '' ? 'uploads' : ('uploads/' . $folder);
    }

    private function normalizeMediaPath(string $path): string
    {
        $clean = trim(str_replace('\\', '/', $path));
        if ($clean === '') {
            return '';
        }

        if (preg_match('#uploads/.*$#i', $clean, $matches) === 1) {
            return ltrim((string) ($matches[0] ?? ''), '/');
        }

        return ltrim($clean, '/');
    }

    private function mediaTypeFromExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'], true)) {
            return 'image';
        }
        if (in_array($extension, ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'], true)) {
            return 'audio';
        }
        return 'file';
    }

    /**
     * @return array{name:string,path:string,url:string,type:string,extension:string,size_kb:string,modified_at:string}|null
     */
    private function buildLocalMediaItem(string $relativePath): ?array
    {
        $relativePath = $this->normalizeMediaPath($relativePath);
        if ($relativePath === '') {
            return null;
        }

        $absolutePath = public_path($relativePath);
        $realPublic = realpath(public_path()) ?: public_path();
        $realFile = realpath($absolutePath);
        if ($realFile === false || !str_starts_with($realFile, $realPublic) || !File::exists($realFile)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($realFile, PATHINFO_EXTENSION));
        $size = 0;
        $modifiedAt = now()->format('Y-m-d H:i');

        try {
            $size = (int) File::size($realFile);
        } catch (Throwable) {
            $size = 0;
        }

        try {
            $modifiedAt = date('Y-m-d H:i', (int) File::lastModified($realFile));
        } catch (Throwable) {
            $modifiedAt = now()->format('Y-m-d H:i');
        }

        return [
            'name' => (string) basename($realFile),
            'path' => $relativePath,
            'url' => url($relativePath),
            'type' => $this->mediaTypeFromExtension($extension),
            'extension' => $extension,
            'size_kb' => number_format(max(0, $size) / 1024, 1),
            'modified_at' => $modifiedAt,
        ];
    }

    /**
     * @param \Illuminate\Contracts\Filesystem\Filesystem|\Illuminate\Filesystem\FilesystemAdapter $disk
     * @return array{name:string,path:string,url:string,type:string,extension:string,size_kb:string,modified_at:string}|null
     */
    private function buildDiskMediaItem($disk, string $path, string $diskName): ?array
    {
        $relativePath = $this->normalizeMediaPath($path);
        if ($relativePath === '') {
            return null;
        }

        $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedMediaExtensions(), true)) {
            return null;
        }

        $size = 0;
        $modifiedAt = now()->format('Y-m-d H:i');
        $url = $this->mediaFileUrl($relativePath, ['mode' => 'disk', 'disk' => $diskName]);

        try {
            $size = (int) $disk->size($relativePath);
        } catch (Throwable) {
            $size = 0;
        }

        try {
            $modifiedAt = date('Y-m-d H:i', (int) $disk->lastModified($relativePath));
        } catch (Throwable) {
            $modifiedAt = now()->format('Y-m-d H:i');
        }

        if ($url === '') {
            $url = '/' . ltrim($relativePath, '/');
        }

        return [
            'name' => (string) basename($relativePath),
            'path' => $relativePath,
            'url' => $url,
            'type' => $this->mediaTypeFromExtension($extension),
            'extension' => $extension,
            'size_kb' => number_format(max(0, $size) / 1024, 1),
            'modified_at' => $modifiedAt,
        ];
    }

    /**
     * @param array{mode:string,disk:string} $storage
     */
    private function mediaFileUrl(string $relativePath, array $storage): string
    {
        if (($storage['mode'] ?? '') === 'disk') {
            try {
                return (string) Storage::disk((string) $storage['disk'])->url($relativePath);
            } catch (Throwable) {
                return '';
            }
        }

        return url($relativePath);
    }

    private function clearBrandingAssetReferences(string $relativePath, string $absoluteUrl = ''): void
    {
        $candidates = array_values(array_filter(array_unique([
            $this->normalizeAssetReference($relativePath),
            $this->normalizeAssetReference($absoluteUrl),
            $this->normalizeAssetReference(url($relativePath)),
        ])));
        if (empty($candidates)) {
            return;
        }

        $settings = [
            'ui.logo_url' => AppSettings::get('ui.logo_url', ''),
            'ui.favicon_url' => AppSettings::get('ui.favicon_url', ''),
            'ui.app_icon_url' => AppSettings::get('ui.app_icon_url', ''),
            'ui.notification_sound_url' => AppSettings::get('ui.notification_sound_url', ''),
        ];

        $updates = [];
        foreach ($settings as $key => $value) {
            $normalized = $this->normalizeAssetReference((string) $value);
            if ($normalized !== '' && in_array($normalized, $candidates, true)) {
                $updates[$key] = '';
            }
        }

        if (!empty($updates)) {
            AppSettings::putMany($updates);
        }
    }

    private function normalizeAssetReference(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $candidate = $value;
        if (preg_match('/^(https?:)?\/\//i', $value) === 1) {
            $candidate = (string) (parse_url($value, PHP_URL_PATH) ?? '');
        }

        $candidate = str_replace('\\', '/', $candidate);
        if (preg_match('#uploads/.*$#i', $candidate, $matches) === 1) {
            return ltrim((string) ($matches[0] ?? ''), '/');
        }

        return ltrim($candidate, '/');
    }

    /**
     * @return array<int, int>
     */
    private function roleUserCounts(): array
    {
        $tables = config('permission.table_names', []);
        $pivotTable = (string) ($tables['model_has_roles'] ?? '');
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

    private function computeHotReloadSignature(): string
    {
        $files = [
            public_path('css/haarray.app.css'),
            public_path('js/haarray.app.js'),
            base_path('routes/web.php'),
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/dashboard.blade.php'),
            resource_path('views/settings/index.blade.php'),
            resource_path('views/settings/users.blade.php'),
            resource_path('views/settings/media.blade.php'),
            resource_path('views/settings/rbac.blade.php'),
            resource_path('views/settings/rbac-create.blade.php'),
            resource_path('views/settings/rbac-edit.blade.php'),
            resource_path('views/docs/starter-kit.blade.php'),
        ];

        $buffer = [];
        foreach ($files as $file) {
            if (!File::exists($file)) {
                continue;
            }

            try {
                $buffer[] = $file . ':' . (string) File::lastModified($file);
            } catch (Throwable) {
                // Ignore unreadable files in hot-reload signature generation.
            }
        }

        if (empty($buffer)) {
            return sha1((string) microtime(true));
        }

        return sha1(implode('|', $buffer));
    }
}
