<?php
// FILE: app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Http\Services\MLSuggestionService;
use App\Http\Services\MarketDataService;
use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\GoldPosition;
use App\Models\IPO;
use App\Models\IpoPosition;
use App\Models\Suggestion;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly MLSuggestionService $mlSuggestionService,
    ) {}

    public function index()
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $monthStart = now()->startOfMonth();

        $monthlyIncome = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'credit')
            ->whereDate('transaction_date', '>=', $monthStart)
            ->sum('amount');

        $monthlySpend = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'debit')
            ->whereDate('transaction_date', '>=', $monthStart)
            ->sum('amount');

        $savingsRate = $monthlyIncome > 0
            ? max(-100, min((($monthlyIncome - $monthlySpend) / $monthlyIncome) * 100, 100))
            : 0;

        $cashTypes = ['bank', 'cash', 'wallet', 'esewa', 'khalti'];
        $idleCash = (float) Account::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('type', $cashTypes)
            ->sum('balance');

        $bankBalance = (float) Account::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->sum('balance');

        $goldInvested = (float) GoldPosition::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(grams * buy_price_per_gram), 0) as total')
            ->value('total');
        $goldCurrent = (float) GoldPosition::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(grams * COALESCE(current_price_per_gram, buy_price_per_gram)), 0) as total')
            ->value('total');

        $ipoInvested = (float) IpoPosition::query()
            ->where('user_id', $user->id)
            ->sum('invested_amount');
        $ipoCurrent = (float) IpoPosition::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(units_allotted * COALESCE(current_price, 0)), 0) as total')
            ->value('total');

        $netWorth = $bankBalance + max($goldCurrent, 0) + max($ipoCurrent, 0);

        $stats = [
            'net_worth' => $netWorth,
            'monthly_spend' => $monthlySpend,
            'savings_rate' => round($savingsRate, 1),
            'idle_cash' => $idleCash,
            'monthly_income' => $monthlyIncome,
            'gold_pl' => $goldCurrent - $goldInvested,
            'ipo_pl' => $ipoCurrent - $ipoInvested,
        ];

        $transactions = Transaction::query()
            ->with(['category', 'account'])
            ->where('user_id', $user->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (Transaction $tx): array {
                $categoryName = (string) optional($tx->category)->name ?: 'Other';
                $label = (string) ($tx->title ?: $tx->notes ?: ($tx->type === 'credit' ? 'Income' : 'Expense'));

                return [
                    'icon' => $tx->type === 'credit' ? '💼' : '💸',
                    'name' => $label,
                    'sub' => $tx->transaction_date?->format('M d') . ' · ' . $categoryName,
                    'type' => $tx->type,
                    'amount' => (float) $tx->amount,
                ];
            })
            ->values()
            ->all();

        if (empty($transactions)) {
            $transactions[] = [
                'icon' => '🧾',
                'name' => 'No transactions yet',
                'sub' => 'Start by logging your first expense',
                'type' => 'debit',
                'amount' => 0,
            ];
        }

        $ipos = IPO::query()
            ->whereIn('status', ['open', 'upcoming'])
            ->orderByRaw("FIELD(status, 'open', 'upcoming')")
            ->orderBy('close_date')
            ->limit(5)
            ->get()
            ->map(function (IPO $ipo): array {
                $openDate = $ipo->open_date?->format('M d, Y') ?? 'TBD';
                $closeDate = $ipo->close_date?->format('M d, Y') ?? 'TBD';

                return [
                    'name' => (string) $ipo->company_name,
                    'dates' => $openDate . ' – ' . $closeDate,
                    'status' => (string) $ipo->status,
                    'unit' => (float) $ipo->price_per_unit,
                    'min' => (int) $ipo->min_units,
                ];
            })
            ->values()
            ->all();

        if (empty($ipos)) {
            $ipos[] = [
                'name' => 'No IPO records yet',
                'dates' => 'Add IPO entries from Portfolio',
                'status' => 'upcoming',
                'unit' => 100,
                'min' => 10,
            ];
        }

        if (Suggestion::query()->where('user_id', $user->id)->count() === 0) {
            $this->mlSuggestionService->generateForUser($user);
        }

        $suggestions = Suggestion::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->latest('id')
            ->limit(3)
            ->get()
            ->map(function (Suggestion $suggestion): array {
                return [
                    'icon' => (string) ($suggestion->icon ?: '💡'),
                    'title' => (string) $suggestion->title,
                    'body' => (string) $suggestion->message,
                    'priority' => (string) $suggestion->priority,
                ];
            })
            ->values()
            ->all();

        if (empty($suggestions)) {
            $suggestions[] = [
                'icon' => '🧠',
                'title' => 'No active suggestions',
                'body' => 'Add expenses and accounts to unlock smart insights.',
                'priority' => 'low',
            ];
        }

        $marketData = $this->marketData->getCached();
        $market = [
            'gold' => (float) ($marketData['gold'] ?? 0),
            'gold_chg' => ((float) ($marketData['gold_change'] ?? 0) >= 0 ? '+' : '') . number_format((float) ($marketData['gold_change'] ?? 0), 1) . '%',
            'gold_up' => (float) ($marketData['gold_change'] ?? 0) >= 0,
            'nepse' => number_format((float) ($marketData['nepse'] ?? 0), 2),
            'nepse_chg' => ((float) ($marketData['nepse_change'] ?? 0) >= 0 ? '+' : '') . number_format((float) ($marketData['nepse_change'] ?? 0), 1) . '%',
            'nepse_up' => (float) ($marketData['nepse_change'] ?? 0) >= 0,
            'usd_npr' => number_format((float) ($marketData['usd_npr'] ?? 0), 2),
            'usd_chg' => '+0.0%',
            'usd_up' => true,
        ];

        $months = collect(range(5, 0))
            ->map(fn (int $offset) => Carbon::now()->startOfMonth()->subMonths($offset))
            ->values();

        $monthlyRows = Transaction::query()
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as month_key")
            ->selectRaw("SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as credit_total")
            ->selectRaw("SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as debit_total")
            ->where('user_id', $user->id)
            ->whereDate('transaction_date', '>=', Carbon::now()->startOfMonth()->subMonths(5))
            ->groupBy('month_key')
            ->get()
            ->mapWithKeys(function ($row): array {
                return [
                    (string) $row->month_key => [
                        'credit' => (float) $row->credit_total,
                        'debit' => (float) $row->debit_total,
                    ],
                ];
            });

        $labels = [];
        $incomeSeries = [];
        $expenseSeries = [];
        foreach ($months as $month) {
            $key = $month->format('Y-m');
            $labels[] = $month->format('M');

            $row = $monthlyRows->get($key, ['credit' => 0.0, 'debit' => 0.0]);
            $incomeSeries[] = (float) ($row['credit'] ?? 0.0);
            $expenseSeries[] = (float) ($row['debit'] ?? 0.0);
        }

        $chart = [
            'labels' => $labels,
            'income' => $incomeSeries,
            'expense' => $expenseSeries,
        ];

        $quickLogAccounts = Account::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'currency']);

        $quickLogCategories = ExpenseCategory::query()
            ->where(function ($query) use ($user): void {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('dashboard', compact('stats','transactions','ipos','suggestions','market','chart', 'quickLogAccounts', 'quickLogCategories'));
    }
}
