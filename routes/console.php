<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use App\Support\RbacBootstrap;
use App\Support\Notifier;
use App\Support\HealthCheckService;
use App\Http\Services\MLSuggestionService;
use App\Models\Suggestion;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('haarray:permissions:sync {--seed-admins : Create/update default admin users with full access} {--admin-email=* : Extra admin emails to promote as super-admin}', function () {
    /** @var RbacBootstrap $rbac */
    $rbac = app(RbacBootstrap::class);
    $result = $rbac->syncPermissionsAndRoles();

    if (!$result['ok']) {
        $this->error((string) $result['message']);
        return self::FAILURE;
    }

    $this->info("Synced {$result['permissions']} permissions and {$result['roles']} roles.");

    if ($this->option('seed-admins')) {
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

        $admins = (array) $this->option('admin-email');
        foreach ($admins as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '') {
                continue;
            }
            $seedUsers[] = [
                'name' => Str::headline(Str::before($email, '@')),
                'email' => $email,
                'password' => $defaultPassword,
                'role' => 'super-admin',
            ];
        }

        $seed = $rbac->ensureUsers($seedUsers);
        $this->info("Admin users ensured. Created {$seed['created']}, updated {$seed['updated']}.");
        $this->line("Default admin password from HAARRAY_ADMIN_PASSWORD (fallback: {$defaultPassword}).");
    }

    return self::SUCCESS;
})->purpose('Sync role/permission matrix and optionally seed default admin users');

Artisan::command('haarray:starter:setup {--seed-admins : Seed/update default admin accounts}', function () {
    $this->comment('Preparing starter kit for production-style usage...');

    $this->call('haarray:permissions:sync', [
        '--seed-admins' => (bool) $this->option('seed-admins'),
    ]);

    $this->call('optimize:clear');
    $this->call('migrate:status');

    $this->line('');
    $this->info('Shared hosting cron recommendations:');
    $this->line('* * * * * php artisan schedule:run >> /dev/null 2>&1');
    $this->line('* * * * * php artisan queue:work --stop-when-empty --tries=1 --timeout=90 >> /dev/null 2>&1');

    $this->line('');
    $this->info('Writable paths checklist:');
    $paths = ['storage', 'bootstrap/cache', 'public/uploads', '.env'];
    foreach ($paths as $path) {
        $absolute = base_path($path);
        $writable = File::exists($absolute) ? is_writable($absolute) : is_writable(dirname($absolute));
        $this->line(($writable ? '[OK] ' : '[WARN] ') . $path);
    }

    return self::SUCCESS;
})->purpose('Run starter bootstrap tasks (permissions sync, diagnostics hints, cron guidance)');

Artisan::command('haarray:health:check', function () {
    /** @var HealthCheckService $health */
    $health = app(HealthCheckService::class);
    $report = $health->report();

    $this->info('Haarray Health Check');
    $this->line('Summary: ' . (int) ($report['summary']['ok'] ?? 0) . ' OK / ' . (int) ($report['summary']['warn'] ?? 0) . ' WARN / ' . (int) ($report['summary']['fail'] ?? 0) . ' FAIL');

    foreach ((array) ($report['checks'] ?? []) as $check) {
        $status = strtoupper((string) ($check['status'] ?? 'warn'));
        $label = (string) ($check['label'] ?? 'check');
        $detail = trim((string) ($check['detail'] ?? ''));
        $line = "[{$status}] {$label}";
        if ($detail !== '') {
            $line .= " - {$detail}";
        }
        if ($status === 'FAIL') {
            $this->error($line);
            continue;
        }
        if ($status === 'WARN') {
            $this->warn($line);
            continue;
        }
        $this->line($line);
    }

    return ((int) ($report['summary']['fail'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
})->purpose('Run internal health checks for DB/cache/storage/queue/notifications');

Artisan::command('log:permissions:generate {--seed-admins : Create/update default admin users}', function () {
    return $this->call('haarray:permissions:sync', [
        '--seed-admins' => (bool) $this->option('seed-admins'),
    ]);
})->purpose('Alias for permissions/roles generation for the LOG app');

Artisan::command('log:suggestions:run {--user-id=* : Limit to specific user IDs} {--notify : Push high-priority results as notifications}', function () {
    /** @var MLSuggestionService $ml */
    $ml = app(MLSuggestionService::class);
    /** @var Notifier $notifier */
    $notifier = app(Notifier::class);

    $ids = array_values(array_unique(array_filter(array_map(
        fn ($id) => (int) $id,
        (array) $this->option('user-id')
    ))));

    $query = User::query();
    if (!empty($ids)) {
        $query->whereIn('id', $ids);
    }

    $users = $query->get();
    if ($users->isEmpty()) {
        $this->warn('No users found for suggestion generation.');
        return self::SUCCESS;
    }

    $notify = (bool) $this->option('notify');
    $generated = 0;
    $notified = 0;

    foreach ($users as $user) {
        $ml->generateForUser($user);
        $generated++;

        if (!$notify) {
            continue;
        }

        $top = Suggestion::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->where('priority', 'high')
            ->latest('id')
            ->first();

        if (!$top) {
            continue;
        }

        $result = $notifier->toUser($user, (string) $top->title, (string) $top->message, [
            'channels' => ['in_app', 'telegram'],
            'level' => 'warning',
            'url' => '/suggestions',
        ]);

        $notified += (int) ($result['in_app'] ?? 0) + (int) ($result['telegram'] ?? 0) > 0 ? 1 : 0;
    }

    $this->info("Suggestions generated for {$generated} user(s).");
    if ($notify) {
        $this->line("High-priority alerts sent to {$notified} user(s).");
    }

    return self::SUCCESS;
})->purpose('Generate ML suggestions and optionally notify users');

Artisan::command('log:core:sync {--source= : Path to source core project} {--profile=log : Target profile key from source reflection config} {--dry-run : Preview rsync actions only} {--full : Run full repository mirror instead of shared-path reflection}', function () {
    $source = trim((string) ($this->option('source') ?: env('LOG_CORE_SOURCE_PATH', base_path('../harray-core'))));
    if ($source === '') {
        $this->error('Source path is empty.');
        return self::FAILURE;
    }

    $source = rtrim($source, '/');
    if (!is_dir($source)) {
        $this->error("Source path does not exist: {$source}");
        return self::FAILURE;
    }

    $target = rtrim(base_path(), '/');
    $dryRun = (bool) $this->option('dry-run');
    $runFull = (bool) $this->option('full');

    if ($runFull) {
        $parts = [
            'rsync',
            '-a',
            '--delete',
            '--exclude=.git',
            '--exclude=.env',
            '--exclude=vendor',
            '--exclude=node_modules',
            '--exclude=storage',
            '--exclude=public/uploads',
            $dryRun ? '--dry-run' : '',
            escapeshellarg($source . '/'),
            escapeshellarg($target . '/'),
        ];

        $command = trim(implode(' ', array_filter($parts, fn ($part) => $part !== '')));
        $this->line('Running full mirror: ' . $command);

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(240);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Sync failed.');
            $this->line(trim($process->getErrorOutput() ?: $process->getOutput()));
            return self::FAILURE;
        }

        $output = trim($process->getOutput());
        if ($output !== '') {
            $this->line($output);
        }

        $this->info($dryRun ? 'Dry-run completed (full mirror).' : 'Core sync completed (full mirror).');
        return self::SUCCESS;
    }

    $profile = trim((string) $this->option('profile')) ?: 'log';
    $reflectionConfigPath = $source . '/config/reflection.php';
    if (!is_file($reflectionConfigPath)) {
        $this->error("Source reflection config not found: {$reflectionConfigPath}");
        $this->line('Use --full if you want legacy full mirror mode.');
        return self::FAILURE;
    }

    $reflection = require $reflectionConfigPath;
    if (!is_array($reflection)) {
        $this->error('Invalid source reflection config.');
        return self::FAILURE;
    }

    $globalShared = array_values(array_unique(array_filter(array_map(
        fn ($path) => trim((string) $path, '/'),
        (array) ($reflection['shared_paths'] ?? [])
    ), fn ($path) => $path !== '')));

    $targetProfiles = is_array($reflection['targets'] ?? null) ? $reflection['targets'] : [];
    $targetProfile = is_array($targetProfiles[$profile] ?? null) ? $targetProfiles[$profile] : [];

    $extraShared = array_values(array_unique(array_filter(array_map(
        fn ($path) => trim((string) $path, '/'),
        (array) ($targetProfile['extra_shared_paths'] ?? [])
    ), fn ($path) => $path !== '')));

    $excludePaths = array_values(array_unique(array_filter(array_map(
        fn ($path) => trim((string) $path, '/'),
        (array) ($targetProfile['exclude_paths'] ?? [])
    ), fn ($path) => $path !== '')));

    $localConfigFile = trim((string) ($targetProfile['local_config_file'] ?? '.haarray-reflection.php')) ?: '.haarray-reflection.php';
    $localConfigPath = $target . '/' . ltrim($localConfigFile, '/');
    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;
        if (is_array($localConfig)) {
            $localExtra = array_values(array_unique(array_filter(array_map(
                fn ($path) => trim((string) $path, '/'),
                (array) ($localConfig['extra_shared_paths'] ?? [])
            ), fn ($path) => $path !== '')));
            $localExclude = array_values(array_unique(array_filter(array_map(
                fn ($path) => trim((string) $path, '/'),
                (array) ($localConfig['exclude_paths'] ?? [])
            ), fn ($path) => $path !== '')));

            $extraShared = array_values(array_unique(array_merge($extraShared, $localExtra)));
            $excludePaths = array_values(array_unique(array_merge($excludePaths, $localExclude)));
        }
    }

    $sharedPaths = array_values(array_unique(array_merge($globalShared, $extraShared)));
    if (!empty($excludePaths)) {
        $excludeMap = array_fill_keys($excludePaths, true);
        $sharedPaths = array_values(array_filter($sharedPaths, fn ($path) => !isset($excludeMap[$path])));
    }

    if (empty($sharedPaths)) {
        $this->warn('No shared paths resolved for reflection sync.');
        return self::SUCCESS;
    }

    $synced = 0;
    $removed = 0;
    $skipped = 0;
    foreach ($sharedPaths as $relativePath) {
        $sourceAbsolute = $source . '/' . $relativePath;
        $targetAbsolute = $target . '/' . $relativePath;

        if (is_dir($sourceAbsolute)) {
            File::ensureDirectoryExists($targetAbsolute);
            $command = ['rsync', '-a', '--delete'];
            if ($dryRun) {
                $command[] = '--dry-run';
            }
            $command[] = rtrim($sourceAbsolute, '/') . '/';
            $command[] = rtrim($targetAbsolute, '/') . '/';

            $process = new Process($command, $target);
            $process->setTimeout(240);
            $process->run();
            if (!$process->isSuccessful()) {
                $this->error("Failed syncing directory: {$relativePath}");
                $this->line(trim($process->getErrorOutput() ?: $process->getOutput()));
                return self::FAILURE;
            }

            $this->line('[SYNC DIR] ' . $relativePath);
            $synced++;
            continue;
        }

        if (is_file($sourceAbsolute)) {
            File::ensureDirectoryExists(dirname($targetAbsolute));
            $command = ['rsync', '-a'];
            if ($dryRun) {
                $command[] = '--dry-run';
            }
            $command[] = $sourceAbsolute;
            $command[] = $targetAbsolute;

            $process = new Process($command, $target);
            $process->setTimeout(240);
            $process->run();
            if (!$process->isSuccessful()) {
                $this->error("Failed syncing file: {$relativePath}");
                $this->line(trim($process->getErrorOutput() ?: $process->getOutput()));
                return self::FAILURE;
            }

            $this->line('[SYNC FILE] ' . $relativePath);
            $synced++;
            continue;
        }

        if (is_dir($targetAbsolute) || is_file($targetAbsolute)) {
            if ($dryRun) {
                $this->line('[REMOVE] ' . $relativePath . ' (dry-run)');
            } else {
                if (is_dir($targetAbsolute)) {
                    File::deleteDirectory($targetAbsolute);
                } else {
                    File::delete($targetAbsolute);
                }
                $this->line('[REMOVE] ' . $relativePath);
            }
            $removed++;
            continue;
        }

        $this->line('[SKIP] ' . $relativePath . ' (missing in source/target)');
        $skipped++;
    }

    $this->info("Core shared reflection completed. Synced {$synced}, removed {$removed}, skipped {$skipped}.");
    return self::SUCCESS;
})->purpose('Sync core shared layer into LOG app (segregated reflection mode)');

Schedule::command('log:suggestions:run --notify')
    ->hourly()
    ->withoutOverlapping();
