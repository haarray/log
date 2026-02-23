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

Artisan::command('log:core:sync {--source= : Path to source core project} {--dry-run : Preview rsync actions only}', function () {
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
    $this->line('Running: ' . $command);

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

    $this->info($dryRun ? 'Dry-run completed.' : 'Core sync completed.');
    return self::SUCCESS;
})->purpose('Mirror selected changes from core into this log app (microservice-style workflow)');

Schedule::command('log:suggestions:run --notify')
    ->hourly()
    ->withoutOverlapping();
