<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $validated = $request->validate([
            'type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($validated, $user): void {
            $account = null;
            if (!empty($validated['account_id'])) {
                $account = Account::query()
                    ->where('user_id', $user->id)
                    ->whereKey((int) $validated['account_id'])
                    ->firstOrFail();
            }

            $category = null;
            if (!empty($validated['category_id'])) {
                $category = ExpenseCategory::query()
                    ->where(function ($query) use ($user): void {
                        $query->whereNull('user_id')
                            ->orWhere('user_id', $user->id);
                    })
                    ->whereKey((int) $validated['category_id'])
                    ->firstOrFail();
            }

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
                $delta = $type === 'credit' ? $amount : -$amount;
                $account->forceFill([
                    'balance' => round((float) $account->balance + $delta, 2),
                ])->save();
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

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $transaction->user_id === (int) $user->id, 403);

        DB::transaction(function () use ($transaction): void {
            $account = $transaction->account;

            if ($account) {
                $delta = $transaction->type === 'credit'
                    ? -((float) $transaction->amount)
                    : (float) $transaction->amount;

                $account->forceFill([
                    'balance' => round((float) $account->balance + $delta, 2),
                ])->save();
            }

            $transaction->delete();
        });

        return back()->with('success', 'Transaction deleted.');
    }
}
