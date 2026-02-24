<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(
        private readonly Notifier $notifier,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = ExpenseCategory::query()
            ->where(function ($query) use ($user): void {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $query = Transaction::query()
            ->with(['account', 'category'])
            ->where('user_id', $user->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->filled('type')) {
            $type = strtolower(trim((string) $request->query('type')));
            if (in_array($type, ['credit', 'debit'], true)) {
                $query->where('type', $type);
            }
        }

        $transactions = $query->paginate(20)->withQueryString();

        $summary = [
            'credit' => (float) Transaction::query()->where('user_id', $user->id)->where('type', 'credit')->sum('amount'),
            'debit' => (float) Transaction::query()->where('user_id', $user->id)->where('type', 'debit')->sum('amount'),
        ];
        $summary['net'] = $summary['credit'] - $summary['debit'];

        return view('transactions.index', [
            'accounts' => $accounts,
            'categories' => $categories,
            'transactions' => $transactions,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate($this->transactionRules());

        DB::transaction(function () use ($validated, $user): void {
            $account = $this->resolveAccount($user->id, (int) ($validated['account_id'] ?? 0));
            $category = $this->resolveCategory($user->id, (int) ($validated['category_id'] ?? 0));
            $amount = (float) $validated['amount'];
            $type = (string) $validated['type'];

            Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $account?->id,
                'category_id' => $category?->id,
                'source' => 'manual',
                'type' => $type,
                'amount' => $amount,
                'title' => trim((string) ($validated['title'] ?? '')) ?: null,
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
                'transaction_date' => (string) $validated['transaction_date'],
            ]);

            if ($account) {
                $this->applyAccountDelta($account, $type === 'credit' ? $amount : -$amount);
            }

            $title = $type === 'credit' ? 'Income logged' : 'Expense logged';
            $message = 'Amount NPR ' . number_format($amount, 2) . ' recorded on ' . (string) $validated['transaction_date'] . '.';

            $this->notifier->toUser($user, $title, $message, [
                'channels' => ['in_app', 'telegram'],
                'level' => $type === 'credit' ? 'success' : 'info',
                'url' => route('transactions.index'),
            ]);
        });

        return back()->with('success', 'Transaction saved.');
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $transaction->user_id === (int) $user->id, 403);

        $validated = $request->validate($this->transactionRules());

        DB::transaction(function () use ($validated, $transaction, $user): void {
            $newAccount = $this->resolveAccount($user->id, (int) ($validated['account_id'] ?? 0));
            $newCategory = $this->resolveCategory($user->id, (int) ($validated['category_id'] ?? 0));
            $newAmount = (float) $validated['amount'];
            $newType = (string) $validated['type'];

            $oldAccountId = (int) ($transaction->account_id ?? 0);
            $oldAmount = (float) $transaction->amount;
            $oldType = (string) $transaction->type;
            $newAccountId = (int) ($newAccount?->id ?? 0);

            if ($oldAccountId > 0 && $oldAccountId === $newAccountId) {
                $sameAccount = Account::query()
                    ->where('user_id', $user->id)
                    ->whereKey($oldAccountId)
                    ->first();

                if ($sameAccount) {
                    $delta = $this->reverseDelta($oldType, $oldAmount) + $this->forwardDelta($newType, $newAmount);
                    $this->applyAccountDelta($sameAccount, $delta);
                }
            } else {
                if ($oldAccountId > 0) {
                    $oldAccount = Account::query()
                        ->where('user_id', $user->id)
                        ->whereKey($oldAccountId)
                        ->first();

                    if ($oldAccount) {
                        $this->applyAccountDelta($oldAccount, $this->reverseDelta($oldType, $oldAmount));
                    }
                }

                if ($newAccount) {
                    $this->applyAccountDelta($newAccount, $this->forwardDelta($newType, $newAmount));
                }
            }

            $transaction->update([
                'account_id' => $newAccount?->id,
                'category_id' => $newCategory?->id,
                'type' => $newType,
                'amount' => $newAmount,
                'title' => trim((string) ($validated['title'] ?? '')) ?: null,
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
                'transaction_date' => (string) $validated['transaction_date'],
            ]);
        });

        return back()->with('success', 'Transaction updated.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'slug' => ['nullable', 'string', 'max:64'],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:24'],
        ]);

        $slug = $this->uniqueCategorySlug(
            $user->id,
            trim((string) ($validated['slug'] ?? '')) !== '' ? (string) $validated['slug'] : (string) $validated['name']
        );

        ExpenseCategory::query()->create([
            'user_id' => $user->id,
            'name' => trim((string) $validated['name']),
            'slug' => $slug,
            'icon' => trim((string) ($validated['icon'] ?? '')) ?: 'fa-solid fa-tag',
            'color' => trim((string) ($validated['color'] ?? '')) ?: '#64748b',
            'is_default' => false,
        ]);

        return back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) ($category->user_id ?? 0) === (int) $user->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'slug' => ['nullable', 'string', 'max:64'],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:24'],
        ]);

        $slugSeed = trim((string) ($validated['slug'] ?? '')) !== '' ? (string) $validated['slug'] : (string) $validated['name'];
        $slug = $this->uniqueCategorySlug($user->id, $slugSeed, (int) $category->id);

        $category->update([
            'name' => trim((string) $validated['name']),
            'slug' => $slug,
            'icon' => trim((string) ($validated['icon'] ?? '')) ?: 'fa-solid fa-tag',
            'color' => trim((string) ($validated['color'] ?? '')) ?: '#64748b',
        ]);

        return back()->with('success', 'Category updated.');
    }

    public function deleteCategory(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) ($category->user_id ?? 0) === (int) $user->id, 403);

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $transaction->user_id === (int) $user->id, 403);

        DB::transaction(function () use ($transaction): void {
            $account = $transaction->account;

            if ($account) {
                $this->applyAccountDelta($account, $this->reverseDelta((string) $transaction->type, (float) $transaction->amount));
            }

            $transaction->delete();
        });

        return back()->with('success', 'Transaction deleted.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function transactionRules(): array
    {
        return [
            'type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date'],
        ];
    }

    private function resolveAccount(int $userId, int $accountId): ?Account
    {
        if ($accountId <= 0) {
            return null;
        }

        return Account::query()
            ->where('user_id', $userId)
            ->whereKey($accountId)
            ->firstOrFail();
    }

    private function resolveCategory(int $userId, int $categoryId): ?ExpenseCategory
    {
        if ($categoryId <= 0) {
            return null;
        }

        return ExpenseCategory::query()
            ->where(function ($query) use ($userId): void {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            })
            ->whereKey($categoryId)
            ->firstOrFail();
    }

    private function forwardDelta(string $type, float $amount): float
    {
        return $type === 'credit' ? $amount : -$amount;
    }

    private function reverseDelta(string $type, float $amount): float
    {
        return $type === 'credit' ? -$amount : $amount;
    }

    private function applyAccountDelta(Account $account, float $delta): void
    {
        if ($delta == 0.0) {
            return;
        }

        $account->forceFill([
            'balance' => round((float) $account->balance + $delta, 2),
        ])->save();
    }

    private function uniqueCategorySlug(int $userId, string $seed, ?int $ignoreId = null): string
    {
        $base = Str::slug($seed);
        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $counter = 2;

        while (true) {
            $query = ExpenseCategory::query()
                ->where('user_id', $userId)
                ->where('slug', $slug);

            if ($ignoreId) {
                $query->whereKeyNot($ignoreId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            $slug = $base . '-' . $counter;
            $counter++;
        }
    }
}
