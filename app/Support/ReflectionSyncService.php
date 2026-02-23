<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use RuntimeException;

class ReflectionSyncService
{
    /**
     * @return array<int, string>
     */
    public function targetNames(): array
    {
        $targets = config('reflection.targets', []);
        if (!is_array($targets)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($name) => trim((string) $name),
            array_keys($targets)
        ), fn ($name) => $name !== ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function target(string $name): ?array
    {
        $targets = config('reflection.targets', []);
        if (!is_array($targets) || !isset($targets[$name]) || !is_array($targets[$name])) {
            return null;
        }

        $target = $targets[$name];
        $targetPath = rtrim((string) ($target['path'] ?? ''), '/');
        if ($targetPath === '') {
            return null;
        }

        $remote = trim((string) ($target['remote'] ?? 'origin')) ?: 'origin';
        $branch = trim((string) ($target['branch'] ?? 'main')) ?: 'main';
        $localConfigFile = trim((string) ($target['local_config_file'] ?? '.haarray-reflection.php')) ?: '.haarray-reflection.php';

        $globalShared = $this->normalizePaths((array) config('reflection.shared_paths', []));
        $extraShared = $this->normalizePaths((array) ($target['extra_shared_paths'] ?? []));
        $exclude = $this->normalizePaths((array) ($target['exclude_paths'] ?? []));

        $localConfig = $this->targetLocalConfig($targetPath, $localConfigFile);
        $localExtra = $this->normalizePaths((array) ($localConfig['extra_shared_paths'] ?? []));
        $localExclude = $this->normalizePaths((array) ($localConfig['exclude_paths'] ?? []));

        $sharedPaths = $this->normalizePaths(array_merge($globalShared, $extraShared, $localExtra));
        $excludePaths = $this->normalizePaths(array_merge($exclude, $localExclude));

        if (!empty($excludePaths)) {
            $excludeSet = array_fill_keys($excludePaths, true);
            $sharedPaths = array_values(array_filter($sharedPaths, fn ($path) => !isset($excludeSet[$path])));
        }

        return [
            'name' => $name,
            'path' => $targetPath,
            'remote' => $remote,
            'branch' => $branch,
            'shared_paths' => $sharedPaths,
            'exclude_paths' => $excludePaths,
            'local_config_file' => $localConfigFile,
        ];
    }

    /**
     * @param array<string, mixed> $target
     * @return array{synced:int,removed:int,skipped:int,operations:array<int,string>}
     */
    public function sync(array $target, bool $dryRun = false): array
    {
        $targetPath = rtrim((string) ($target['path'] ?? ''), '/');
        if ($targetPath === '') {
            throw new RuntimeException('Target path is empty.');
        }
        if (!is_dir($targetPath)) {
            throw new RuntimeException("Target path not found: {$targetPath}");
        }

        $sharedPaths = $this->normalizePaths((array) ($target['shared_paths'] ?? []));
        if (empty($sharedPaths)) {
            throw new RuntimeException('No shared paths configured for reflection.');
        }

        $sourceRoot = rtrim(base_path(), '/');
        $operations = [];
        $synced = 0;
        $removed = 0;
        $skipped = 0;

        foreach ($sharedPaths as $relativePath) {
            $sourceAbsolute = $sourceRoot . '/' . $relativePath;
            $targetAbsolute = $targetPath . '/' . $relativePath;

            if (is_dir($sourceAbsolute)) {
                File::ensureDirectoryExists($targetAbsolute);

                $command = ['rsync', '-a', '--delete'];
                if ($dryRun) {
                    $command[] = '--dry-run';
                }
                $command[] = rtrim($sourceAbsolute, '/') . '/';
                $command[] = rtrim($targetAbsolute, '/') . '/';

                $result = $this->run($command, $sourceRoot);
                $operations[] = '[SYNC DIR] ' . $relativePath . $this->formatOutputSuffix($result);
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

                $result = $this->run($command, $sourceRoot);
                $operations[] = '[SYNC FILE] ' . $relativePath . $this->formatOutputSuffix($result);
                $synced++;
                continue;
            }

            if (is_dir($targetAbsolute) || is_file($targetAbsolute)) {
                if ($dryRun) {
                    $operations[] = '[REMOVE] ' . $relativePath . ' (dry-run)';
                    $removed++;
                    continue;
                }

                if (is_dir($targetAbsolute)) {
                    File::deleteDirectory($targetAbsolute);
                } else {
                    File::delete($targetAbsolute);
                }

                $operations[] = '[REMOVE] ' . $relativePath;
                $removed++;
                continue;
            }

            $operations[] = '[SKIP] ' . $relativePath . ' (missing in source/target)';
            $skipped++;
        }

        return [
            'synced' => $synced,
            'removed' => $removed,
            'skipped' => $skipped,
            'operations' => $operations,
        ];
    }

    /**
     * @param array<int, string> $paths
     * @return array<int, string>
     */
    private function normalizePaths(array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            $value = str_replace('\\\\', '/', trim((string) $path));
            $value = trim($value, '/');
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, mixed>
     */
    private function targetLocalConfig(string $targetPath, string $fileName): array
    {
        $configPath = rtrim($targetPath, '/') . '/' . ltrim($fileName, '/');
        if (!is_file($configPath)) {
            return [];
        }

        $payload = require $configPath;
        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<int, string> $command
     * @return array{output:string,error:string}
     */
    private function run(array $command, string $workingDirectory): array
    {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout(240);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException($message !== '' ? $message : 'Command failed: ' . implode(' ', $command));
        }

        return [
            'output' => trim($process->getOutput()),
            'error' => trim($process->getErrorOutput()),
        ];
    }

    /**
     * @param array{output:string,error:string} $result
     */
    private function formatOutputSuffix(array $result): string
    {
        $line = trim($result['output']);
        if ($line === '') {
            return '';
        }

        $compact = preg_replace('/\s+/', ' ', $line) ?: $line;
        $compact = mb_substr($compact, 0, 180);

        return ' -> ' . $compact;
    }
}
