# Notifier Helper Tutorial

Use the global `Notifier` service from controllers/jobs/services to dispatch app + Telegram notifications without UI automation rules.

## Why this pattern

- Keeps business logic near the CRUD action (`store`, `update`, `destroy`)
- Avoids broad route-level automation noise
- Honors each user's channel preferences

## Helper methods

```php
app(\App\Support\Notifier::class)->toUser($user, $title, $message, $options = []);
app(\App\Support\Notifier::class)->toIds([1,2,3], $title, $message, $options = []);
app(\App\Support\Notifier::class)->toRole('manager', $title, $message, $options = []);
app(\App\Support\Notifier::class)->toAdmins($title, $message, $options = []);
app(\App\Support\Notifier::class)->toAll($title, $message, $options = []);
```

## Options

- `level`: `info | success | warning | error`
- `channels`: `['in_app']`, `['telegram']`, or both
- `url`: optional deep link shown in in-app feed

## Example: send after create

```php
public function store(Request $request, Notifier $notifier)
{
    $invoice = Invoice::create([...]);

    $notifier->toRole(
        'manager',
        'Invoice Created',
        "Invoice {$invoice->invoice_no} was created.",
        [
            'level' => 'success',
            'channels' => ['in_app', 'telegram'],
            'url' => route('invoices.show', $invoice),
        ]
    );

    return back()->with('success', 'Invoice created.');
}
```

## Channel behavior

- In-app only sends when notifications table exists and user has `receive_in_app_notifications=1`.
- Telegram only sends when user has `receive_telegram_notifications=1` and a valid `telegram_chat_id`.

