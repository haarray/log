# Finance Log Workflow

This tutorial covers the end-to-end flow shipped in `haarray/log`.

## 1) Accounts first

- Open `/accounts`
- Create at least one bank/wallet/cash account
- Keep balances updated to improve suggestion quality

## 2) Log transactions

- Open `/transactions`
- Add debit (expense) and credit (income) entries
- Link account + category for cleaner analytics

## 3) Track investments

- Open `/portfolio`
- Add IPO master records (if you manage listings)
- Add your IPO positions and gold positions

## 4) Generate insights

- Open `/suggestions` and click `Refresh`
- Or run from CLI:

```bash
php artisan log:suggestions:run --notify
```

## 5) Telegram logging

After setting bot token + webhook:

- `/link your-email@example.com`
- `/log 250 food tea`
- `/income 5000 salary project`
- `/balance`

Bot writes directly into `transactions` and updates the Telegram wallet account.

## 6) Keep sync with core

Use core sync command when base scaffold changes:

```bash
php artisan log:core:sync --dry-run
php artisan log:core:sync
```

Or helper script:

```bash
./scripts/sync-core.sh --dry-run
./scripts/sync-core.sh
```
