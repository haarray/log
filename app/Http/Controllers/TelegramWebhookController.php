<?php

namespace App\Http\Controllers;

use App\Http\Services\TelegramNotificationService;
use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\TelegramUpdate;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramNotificationService $telegram,
        private readonly Notifier $notifier,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $updateId = (int) ($payload['update_id'] ?? 0);

        if ($updateId <= 0) {
            return response()->json(['ok' => true, 'message' => 'ignored']);
        }

        if (TelegramUpdate::query()->where('update_id', $updateId)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $messageData = $payload['message'] ?? $payload['edited_message'] ?? null;
        if (!is_array($messageData)) {
            TelegramUpdate::query()->create([
                'update_id' => $updateId,
                'payload' => $payload,
                'processed_at' => now(),
            ]);

            return response()->json(['ok' => true, 'message' => 'no-message']);
        }

        $chatId = (string) data_get($messageData, 'chat.id', '');
        $text = trim((string) data_get($messageData, 'text', ''));

        $update = TelegramUpdate::query()->create([
            'update_id' => $updateId,
            'chat_id' => $chatId !== '' ? $chatId : null,
            'payload' => $payload,
            'processed_at' => null,
        ]);

        if ($chatId === '' || $text === '') {
            $update->forceFill(['processed_at' => now()])->save();
            return response()->json(['ok' => true, 'message' => 'no-text']);
        }

        if ($this->isCommand($text, 'start')) {
            $this->sendHelp($chatId);
            $update->forceFill(['processed_at' => now()])->save();
            return response()->json(['ok' => true, 'message' => 'help']);
        }

        if ($this->isCommand($text, 'link')) {
            $this->handleLink($chatId, $text);
            $update->forceFill(['processed_at' => now()])->save();
            return response()->json(['ok' => true, 'message' => 'linked']);
        }

        $user = User::query()->where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            $this->telegram->sendMessage(
                $chatId,
                "Account not linked. Use: <code>/link your-email@example.com</code>"
            );

            $update->forceFill(['processed_at' => now()])->save();
            return response()->json(['ok' => true, 'message' => 'unlinked']);
        }

        if ($this->isCommand($text, 'balance')) {
            $this->sendBalance($chatId, $user);
            $update->forceFill(['processed_at' => now()])->save();
            return response()->json(['ok' => true, 'message' => 'balance']);
        }

        if ($this->isCommand($text, 'log') || $this->isCommand($text, 'expense')) {
            $this->handleExpenseCommand($chatId, $user, $text);
            $update->forceFill(['processed_at' => now()])->save();
            return response()->json(['ok' => true, 'message' => 'expense']);
        }

        if ($this->isCommand($text, 'income')) {
            $this->handleIncomeCommand($chatId, $user, $text);
            $update->forceFill(['processed_at' => now()])->save();
            return response()->json(['ok' => true, 'message' => 'income']);
        }

        $this->sendHelp($chatId);
        $update->forceFill(['processed_at' => now()])->save();

        return response()->json(['ok' => true, 'message' => 'unknown']);
    }

    private function handleLink(string $chatId, string $text): void
    {
        $email = strtolower(trim((string) Str::of($text)->after('/link')->trim()));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->telegram->sendMessage($chatId, 'Invalid email. Use <code>/link your-email@example.com</code>.');
            return;
        }

        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            $this->telegram->sendMessage($chatId, 'No account found for that email.');
            return;
        }

        $user->forceFill([
            'telegram_chat_id' => $chatId,
            'receive_telegram_notifications' => true,
        ])->save();

        $this->telegram->sendMessage(
            $chatId,
            'Linked successfully. You can now use: <code>/log 250 food tea</code>, <code>/income 5000 salary</code>, <code>/balance</code>.'
        );
    }

    private function handleExpenseCommand(string $chatId, User $user, string $text): void
    {
        $parsed = $this->parseAmountCategoryNote($text);
        if ($parsed === null) {
            $this->telegram->sendMessage($chatId, 'Format: <code>/log 250 food tea</code>');
            return;
        }

        [$amount, $categorySlug, $note] = $parsed;

        DB::transaction(function () use ($user, $amount, $categorySlug, $note): void {
            $account = $this->defaultTelegramAccount($user);
            $category = $this->resolveCategory($user, $categorySlug);

            Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $category?->id,
                'source' => 'telegram',
                'type' => 'debit',
                'amount' => $amount,
                'title' => 'Telegram expense',
                'notes' => $note,
                'transaction_date' => now()->toDateString(),
            ]);

            $account->forceFill([
                'balance' => round((float) $account->balance - $amount, 2),
            ])->save();
        });

        $this->notifier->toUser($user, 'Expense Logged via Telegram', 'NPR ' . number_format($amount, 2) . ' was added to your expense log.', [
            'channels' => ['in_app'],
            'level' => 'info',
            'url' => route('transactions.index'),
        ]);

        $this->telegram->sendMessage(
            $chatId,
            'Expense saved: <b>NPR ' . number_format($amount, 2) . '</b> (' . e($categorySlug) . ')' . ($note ? ' - ' . e($note) : '')
        );
    }

    private function handleIncomeCommand(string $chatId, User $user, string $text): void
    {
        $parsed = $this->parseAmountCategoryNote($text);
        if ($parsed === null) {
            $this->telegram->sendMessage($chatId, 'Format: <code>/income 50000 salary client payment</code>');
            return;
        }

        [$amount, $categorySlug, $note] = $parsed;

        DB::transaction(function () use ($user, $amount, $categorySlug, $note): void {
            $account = $this->defaultTelegramAccount($user);
            $category = $this->resolveCategory($user, $categorySlug);

            Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $category?->id,
                'source' => 'telegram',
                'type' => 'credit',
                'amount' => $amount,
                'title' => 'Telegram income',
                'notes' => $note,
                'transaction_date' => now()->toDateString(),
            ]);

            $account->forceFill([
                'balance' => round((float) $account->balance + $amount, 2),
            ])->save();
        });

        $this->notifier->toUser($user, 'Income Logged via Telegram', 'NPR ' . number_format($amount, 2) . ' was added to your income log.', [
            'channels' => ['in_app'],
            'level' => 'success',
            'url' => route('transactions.index', ['type' => 'credit']),
        ]);

        $this->telegram->sendMessage(
            $chatId,
            'Income saved: <b>NPR ' . number_format($amount, 2) . '</b> (' . e($categorySlug) . ')' . ($note ? ' - ' . e($note) : '')
        );
    }

    private function sendBalance(string $chatId, User $user): void
    {
        $total = (float) Account::query()->where('user_id', $user->id)->where('is_active', true)->sum('balance');
        $top = Account::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('balance')
            ->limit(4)
            ->get();

        $lines = ['<b>Current Balance</b>', 'Total: NPR ' . number_format($total, 2), ''];
        foreach ($top as $account) {
            $lines[] = '• ' . e((string) $account->name) . ': NPR ' . number_format((float) $account->balance, 2);
        }

        $this->telegram->sendMessage($chatId, implode(PHP_EOL, $lines));
    }

    private function sendHelp(string $chatId): void
    {
        $this->telegram->sendMessage(
            $chatId,
            implode(PHP_EOL, [
                '<b>Haarray Log Bot</b>',
                'Link account: <code>/link your-email@example.com</code>',
                'Log expense: <code>/log 250 food tea</code>',
                'Log income: <code>/income 12000 salary project</code>',
                'Check balance: <code>/balance</code>',
            ])
        );
    }

    private function isCommand(string $text, string $command): bool
    {
        return preg_match('/^\/' . preg_quote($command, '/') . '\b/i', $text) === 1;
    }

    /**
     * @return array{0:float,1:string,2:string|null}|null
     */
    private function parseAmountCategoryNote(string $text): ?array
    {
        $pattern = '/^\/(?:log|expense|income)\s+([0-9]+(?:\.[0-9]+)?)\s+([a-zA-Z0-9_-]+)(?:\s+(.+))?$/';
        if (preg_match($pattern, trim($text), $matches) !== 1) {
            return null;
        }

        $amount = (float) $matches[1];
        if ($amount <= 0) {
            return null;
        }

        $category = Str::slug((string) $matches[2]);
        $note = isset($matches[3]) ? trim((string) $matches[3]) : null;

        return [$amount, $category !== '' ? $category : 'other', $note !== '' ? $note : null];
    }

    private function defaultTelegramAccount(User $user): Account
    {
        return Account::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'name' => 'Telegram Wallet',
            ],
            [
                'institution' => 'Telegram',
                'type' => 'wallet',
                'currency' => 'NPR',
                'balance' => 0,
                'is_active' => true,
            ]
        );
    }

    private function resolveCategory(User $user, string $slug): ?ExpenseCategory
    {
        $slug = Str::slug($slug);
        if ($slug === '') {
            $slug = 'other';
        }

        $category = ExpenseCategory::query()
            ->whereNull('user_id')
            ->where('slug', $slug)
            ->first();

        if ($category) {
            return $category;
        }

        return ExpenseCategory::query()->create([
            'user_id' => $user->id,
            'name' => Str::headline($slug),
            'slug' => $slug,
            'icon' => 'fa-solid fa-tag',
            'color' => '#64748b',
            'is_default' => false,
        ]);
    }
}
