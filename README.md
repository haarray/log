# Haarray Log

Haarray Log is a Laravel-based expense tracker and personal portfolio manager built from the Haarray core starter. It is designed for real personal finance use: accounts, transactions, IPO tracking, gold positions, ML suggestions, and Telegram-driven logging.

## Core highlights

- Accounts module (bank/wallet/cash)
- Transaction ledger CRUD (debit/credit with category/account links)
- Category CRUD (user-owned categories + default system categories)
- Portfolio module (IPO positions + gold positions)
- ML suggestion feed (PHP-ML + rule-based checks)
- Salary intelligence (delay/mismatch detection + tax/SSF estimate)
- Live market sync (CDSC + ShareSansar + Hamro Patro ingestion)
- Telegram bot webhook intake (`/link`, `/log`, `/income`, `/balance`)
- In-app + Telegram notification dispatch via `App\Support\Notifier`
- Root access reservation (`HAARRAY_ROOT_ADMIN_EMAIL`)
- Terms acceptance on signup
- Core-to-log sync command for microservice-style workflow

## Built-in roles and access

Roles are managed by Spatie Permission + `App\Support\RbacBootstrap`.

- `super-admin`: full access, reserved for root admin email
- `admin`: full access except root reservation logic
- `manager`: operational access
- `user`: app usage access (dashboard/accounts/transactions/portfolio/suggestions/notifications)

`super-admin` is only retained for:

- `prateekbhujelpb@gmail.com` (or your `HAARRAY_ROOT_ADMIN_EMAIL`)

Any other account attempting `super-admin` is normalized to `admin`.

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan log:setup:local --fresh --seed-admins
php artisan serve
```

Open:

- `http://127.0.0.1:8000`

## Default seeded users

Seeder: `Database\Seeders\AdminAccessSeeder`

- `prateekbhujelpb@gmail.com` (`super-admin`)
- `admin@admin.com` (`admin`)

Password source:

- `.env` -> `HAARRAY_ADMIN_PASSWORD`
- fallback used by seeder: `Admin@12345`

## Telegram bot setup

Configure in `.env`:

```env
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_BOT_USERNAME=haarray_bot
APP_URL=https://your-domain-or-localhost
TELEGRAM_BOT_WEBHOOK_URL=${APP_URL}/telegram/webhook
```

Set webhook once:

```bash
curl -X POST "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook" \
  -d "url=${APP_URL}/telegram/webhook"
```

Bot commands:

- `/link your-email@example.com`
- `/log 250 food tea`
- `/income 5000 salary project`
- `/balance`

## Key routes

- `GET /dashboard`
- `GET /accounts`
- `GET /transactions`
- `GET /portfolio`
- `GET /suggestions`
- `POST /telegram/webhook`

## Operations commands

```bash
# One-shot local bootstrap (creates DB when possible, migrates, seeds, syncs perms, market warmup)
php artisan log:setup:local --fresh --seed-admins

# Sync roles/permissions + optional admins
php artisan haarray:permissions:sync --seed-admins

# Alias command
php artisan log:permissions:generate --seed-admins

# Generate ML suggestions for all users
php artisan log:suggestions:run

# Generate + push high-priority alerts
php artisan log:suggestions:run --notify

# Sync live IPO board + stock prices + gold price
php artisan log:market:sync
php artisan log:market:sync --notify

# Mirror shared core layer into this repo
php artisan log:core:sync --dry-run
php artisan log:core:sync

# Optional legacy full mirror mode
php artisan log:core:sync --full --dry-run
php artisan log:core:sync --full
```

## Core -> Log sync workflow

This project includes a microservice-style sync helper so your core updates can be reflected in `log`.

Configure source path in `.env`:

```env
LOG_CORE_SOURCE_PATH=../harray-core
```

Then run shared reflection:

```bash
php artisan log:core:sync --dry-run
php artisan log:core:sync
```

Or helper script:

```bash
./scripts/sync-core.sh --dry-run
./scripts/sync-core.sh
```

Shared reflection uses `harray-core/config/reflection.php` + local guard file `.haarray-reflection.php` so app-specific domain code stays segregated.

Use full mirror only when intentionally re-baselining from core (`--full`).

Full mirror excludes:

- `.git`
- `.env`
- `vendor`
- `node_modules`
- `storage`
- `public/uploads`

## Automatic core -> log reflection

By default, core changes are **not** mirrored automatically just by `git push`.

Use either:

1. Core-side helper:

```bash
cd ../harray-core
./scripts/push-and-reflect.sh log
```

2. Log-side pull sync:

```bash
php artisan log:core:sync
```

To make it fully automatic in GitHub, add a CI workflow in `harray-core` that runs reflection and pushes to `haarray/log` on every push to `main` (requires a repo write token/secret).

## Live market sources

- IPO board: CDSC primary + ShareSansar fallback merge
- Today share prices (LTP): ShareSansar
- Gold Hallmark (tola): Hamro Patro + goldpricenepal fallback
- USD/NPR: NRB API primary + open.er-api fallback

Environment keys:

- `MARKET_CDSC_IPO_URL`
- `MARKET_NRB_URL`
- `MARKET_FOREX_URL`
- `MARKET_NEPSE_URL`
- `MARKET_GOLD_URL`
- `MARKET_SHARESANSAR_BASE_URL`
- `MARKET_SHARESANSAR_TODAY_SHARE_PATH`
- `MARKET_SHARESANSAR_EXISTING_ISSUE_ENDPOINT`
- `MARKET_SHARESANSAR_UPCOMING_ISSUE_ENDPOINT`
- `MARKET_HAMROPATRO_GOLD_URL`

Scheduler defaults:

- `log:market:sync --notify` at minute `02` every hour
- `log:suggestions:run --notify` at minute `08` every hour

Shared-hosting fallback:

- Inline automation can run market/suggestion refresh during normal GET requests when cron is unavailable.
- Control via:
  - `HAARRAY_INLINE_AUTOMATION=true`
  - `HAARRAY_INLINE_MARKET_REFRESH_EVERY_SECONDS=3600`
  - `HAARRAY_INLINE_SUGGESTIONS_EVERY_SECONDS=900`

## Storage and git hygiene

`public/uploads` is ignored from git. Uploaded files stay local/runtime only.

## Hosting modes

Works with:

- `php artisan serve`
- `php -S 127.0.0.1:8000 server.php`
- XAMPP path deployment (`localhost/<appname>`) through root `.htaccess`/`server.php`

For permission issues on macOS/XAMPP:

```bash
chmod -R 0777 storage bootstrap/cache public/uploads
chmod 0666 .env
chmod +x artisan server.php index.php scripts/sync-core.sh
```
