# Reflection Sync (Core -> Apps)

This guide explains how to reflect the shared Haarray core layer into downstream apps such as `log`.

## Why

- Keep one shared UI/infra layer in `harray-core`
- Keep each app domain (`log`, future apps) separate
- Sync shared code without overwriting app-specific modules

## Config

`config/reflection.php` defines:

- `shared_paths`: core files/folders that should be reflected
- `targets`: downstream app definitions (`path`, `remote`, `branch`)

For `log`, path defaults to:

- `../log`

You can override with `.env`:

```env
HAARRAY_REFLECT_LOG_PATH=../log
HAARRAY_REFLECT_LOG_REMOTE=origin
HAARRAY_REFLECT_LOG_BRANCH=main
```

## Target-side segregation

Each target can keep a local file:

- `.haarray-reflection.php`

Supported keys:

- `extra_shared_paths`: add more paths for that target
- `exclude_paths`: block specific shared paths for that target

This is how app-specific code remains isolated from core reflection.

## Commands

List targets:

```bash
php artisan haarray:reflect:list
```

Dry-run sync:

```bash
php artisan haarray:reflect:sync log --dry-run
```

Sync all targets:

```bash
php artisan haarray:reflect:sync
```

Sync + commit + push targets:

```bash
php artisan haarray:reflect:sync log --commit-targets --push-targets
```

Sync + push core head too:

```bash
php artisan haarray:reflect:sync log --commit-targets --push-targets --push-core
```

Wrapper script:

```bash
./scripts/reflect-sync.sh log --dry-run
```

## Safety

- `--dry-run` previews without write.
- By default, push/commit flow aborts on dirty target repo.
- Use `--allow-dirty` only if you intentionally want to proceed.
