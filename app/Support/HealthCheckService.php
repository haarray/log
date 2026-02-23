<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HealthCheckService
{
    /**
     * @return array{
     *   generated_at:string,
     *   summary:array{ok:int,warn:int,fail:int,total:int},
     *   checks:array<int,array{key:string,label:string,status:string,detail:string,value:string}>
     * }
     */
    public function report(): array
    {
        $checks = [
            $this->checkAppKey(),
            $this->checkDatabase(),
            $this->checkCache(),
            $this->checkStoragePaths(),
            $this->checkQueueTables(),
            $this->checkNotificationsTable(),
            $this->checkPermissionTables(),
            $this->checkDiskSpace(),
        ];

        $summary = [
            'ok' => 0,
            'warn' => 0,
            'fail' => 0,
            'total' => count($checks),
        ];

        foreach ($checks as $check) {
            $status = strtolower((string) ($check['status'] ?? 'warn'));
            if ($status === 'ok') {
                $summary['ok']++;
            } elseif ($status === 'fail') {
                $summary['fail']++;
            } else {
                $summary['warn']++;
            }
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'summary' => $summary,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string,value:string}
     */
    private function checkAppKey(): array
    {
        $key = trim((string) config('app.key', ''));
        $ok = $key !== '' && !str_contains($key, 'base64:AAAAAAAA');

        return [
            'key' => 'app_key',
            'label' => 'APP_KEY',
            'status' => $ok ? 'ok' : 'fail',
            'detail' => $ok ? 'Application key is configured.' : 'APP_KEY is missing or invalid.',
            'value' => $key !== '' ? 'set' : 'missing',
        ];
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string,value:string}
     */
    private function checkDatabase(): array
    {
        $connection = (string) config('database.default', 'mysql');

        try {
            DB::connection($connection)->getPdo();
            $dbName = (string) (DB::connection($connection)->getDatabaseName() ?? '');

            return [
                'key' => 'database',
                'label' => 'Database Connection',
                'status' => 'ok',
                'detail' => 'Connected to database successfully.',
                'value' => $connection . ($dbName !== '' ? ':' . $dbName : ''),
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'database',
                'label' => 'Database Connection',
                'status' => 'fail',
                'detail' => $exception->getMessage(),
                'value' => $connection,
            ];
        }
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string,value:string}
     */
    private function checkCache(): array
    {
        $cacheStore = (string) config('cache.default', 'file');
        $cacheKey = 'haarray.health.ping';

        try {
            Cache::put($cacheKey, 'ok', 60);
            $value = (string) Cache::get($cacheKey, '');
            Cache::forget($cacheKey);
            $ok = $value === 'ok';

            return [
                'key' => 'cache',
                'label' => 'Cache Store',
                'status' => $ok ? 'ok' : 'warn',
                'detail' => $ok ? 'Cache write/read test passed.' : 'Cache write/read test returned unexpected value.',
                'value' => $cacheStore,
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'cache',
                'label' => 'Cache Store',
                'status' => 'warn',
                'detail' => $exception->getMessage(),
                'value' => $cacheStore,
            ];
        }
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string,value:string}
     */
    private function checkStoragePaths(): array
    {
        $paths = [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'public/uploads' => public_path('uploads'),
        ];

        $bad = [];
        foreach ($paths as $label => $path) {
            $target = is_dir($path) ? $path : dirname($path);
            if (!is_writable($target)) {
                $bad[] = $label;
            }
        }

        return [
            'key' => 'writable_paths',
            'label' => 'Writable Paths',
            'status' => empty($bad) ? 'ok' : 'warn',
            'detail' => empty($bad)
                ? 'Core writable paths are writable.'
                : 'Not writable: ' . implode(', ', $bad),
            'value' => empty($bad) ? 'all-writable' : 'partial',
        ];
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string,value:string}
     */
    private function checkQueueTables(): array
    {
        try {
            $jobs = Schema::hasTable('jobs');
            $failed = Schema::hasTable('failed_jobs');
        } catch (Throwable $exception) {
            return [
                'key' => 'queue_tables',
                'label' => 'Queue Tables',
                'status' => 'warn',
                'detail' => $exception->getMessage(),
                'value' => 'unknown',
            ];
        }

        $status = ($jobs && $failed) ? 'ok' : 'warn';
        $detail = ($jobs && $failed)
            ? 'Queue tables are available.'
            : 'Missing queue tables: ' . implode(', ', array_filter([
                $jobs ? null : 'jobs',
                $failed ? null : 'failed_jobs',
            ]));

        return [
            'key' => 'queue_tables',
            'label' => 'Queue Tables',
            'status' => $status,
            'detail' => $detail,
            'value' => ($jobs ? 'jobs' : 'no-jobs') . '/' . ($failed ? 'failed_jobs' : 'no-failed_jobs'),
        ];
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string,value:string}
     */
    private function checkNotificationsTable(): array
    {
        try {
            $ready = Schema::hasTable('notifications');
        } catch (Throwable $exception) {
            return [
                'key' => 'notifications_table',
                'label' => 'Notifications Table',
                'status' => 'warn',
                'detail' => $exception->getMessage(),
                'value' => 'unknown',
            ];
        }

        return [
            'key' => 'notifications_table',
            'label' => 'Notifications Table',
            'status' => $ready ? 'ok' : 'warn',
            'detail' => $ready ? 'Database notifications are enabled.' : 'Run migrations to enable in-app notifications.',
            'value' => $ready ? 'ready' : 'missing',
        ];
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string,value:string}
     */
    private function checkPermissionTables(): array
    {
        $tables = config('permission.table_names', []);
        $required = ['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'];

        if (!is_array($tables) || empty($tables)) {
            return [
                'key' => 'rbac_tables',
                'label' => 'RBAC Tables',
                'status' => 'warn',
                'detail' => 'Permission table config is missing.',
                'value' => 'unconfigured',
            ];
        }

        $missing = [];
        try {
            foreach ($required as $key) {
                $table = (string) ($tables[$key] ?? '');
                if ($table === '' || !Schema::hasTable($table)) {
                    $missing[] = $key;
                }
            }
        } catch (Throwable $exception) {
            return [
                'key' => 'rbac_tables',
                'label' => 'RBAC Tables',
                'status' => 'warn',
                'detail' => $exception->getMessage(),
                'value' => 'unknown',
            ];
        }

        return [
            'key' => 'rbac_tables',
            'label' => 'RBAC Tables',
            'status' => empty($missing) ? 'ok' : 'warn',
            'detail' => empty($missing) ? 'Spatie permission tables are ready.' : 'Missing: ' . implode(', ', $missing),
            'value' => empty($missing) ? 'ready' : 'partial',
        ];
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string,value:string}
     */
    private function checkDiskSpace(): array
    {
        try {
            $total = @disk_total_space(base_path());
            $free = @disk_free_space(base_path());
            if (!is_numeric($total) || !is_numeric($free) || $total <= 0) {
                throw new \RuntimeException('Disk space API not available.');
            }

            $freePercent = round(($free / $total) * 100, 2);
            $status = $freePercent < 10 ? 'warn' : 'ok';

            return [
                'key' => 'disk_space',
                'label' => 'Disk Space',
                'status' => $status,
                'detail' => "Free disk space: {$freePercent}%",
                'value' => (string) $freePercent,
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'disk_space',
                'label' => 'Disk Space',
                'status' => 'warn',
                'detail' => $exception->getMessage(),
                'value' => 'n/a',
            ];
        }
    }
}
