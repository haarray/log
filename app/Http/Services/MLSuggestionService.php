<?php

namespace App\Http\Services;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\IPO;
use App\Models\Suggestion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * MLSuggestionService
 *
 * Rule-based + PHP-ML powered suggestion engine.
 * Analyzes spending, savings, idle cash, IPOs, market data
 * and generates actionable suggestions for the user.
 *
 * Install PHP-ML: composer require php-ai/php-ml
 */
class MLSuggestionService
{
    public function __construct(
        protected MarketDataService $market,
        protected AdvancedMLService $advancedML,
    ) {}

    /**
     * Generate and store suggestions for a user
     */
    public function generateForUser(User $user): void
    {
        $suggestions = [];

        $suggestions = array_merge($suggestions, $this->checkIPOOpportunities($user));
        $suggestions = array_merge($suggestions, $this->checkSpendingPatterns($user));
        $suggestions = array_merge($suggestions, $this->checkIdleCash($user));
        $suggestions = array_merge($suggestions, $this->checkSavingsRate($user));
        $suggestions = array_merge($suggestions, $this->checkSalarySignals($user));

        // Clear old unread suggestions and insert new ones
        Suggestion::where('user_id', $user->id)->where('is_read', false)->delete();

        foreach ($suggestions as $s) {
            Suggestion::create([
                'user_id'  => $user->id,
                'title'    => $s['title'],
                'message'  => $s['message'],
                'type'     => $s['type'],
                'priority' => $s['priority'],
                'icon'     => $s['icon'],
                'is_read'  => false,
            ]);
        }
    }

    /**
     * Check open/upcoming IPOs vs user's idle cash
     */
    protected function checkIPOOpportunities(User $user): array
    {
        $suggestions = [];

        $idleCash = Account::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('type', ['bank', 'cash', 'wallet', 'esewa', 'khalti'])
            ->sum('balance');

        $openIPOs = IPO::where('status', 'open')
            ->where(function ($query): void {
                $query->whereNull('close_date')
                    ->orWhere('close_date', '>=', now());
            })
            ->get();

        foreach ($openIPOs as $ipo) {
            $minAmount = $ipo->min_units * $ipo->price_per_unit;
            $daysLeft  = $this->wholeDaysUntil($ipo->close_date);

            if ($idleCash >= $minAmount && $daysLeft >= 0) {
                $suggestions[] = [
                    'title'    => "Apply for {$ipo->company_name} IPO — Closes in {$this->dayLabel($daysLeft)}",
                    'message'  => "You have रू " . number_format($idleCash) . " idle. Minimum application: रू " . number_format($minAmount) . " for {$ipo->min_units} units. Closes {$ipo->close_date->format('M d')}.",
                    'type'     => 'ipo',
                    'priority' => 'high',
                    'icon'     => '⚡',
                ];
            }
        }

        // Check upcoming IPOs
        $upcoming = IPO::where('status', 'upcoming')
            ->where('open_date', '<=', now()->addDays(3))
            ->orderBy('open_date')
            ->first();

        if ($upcoming) {
            $daysUntilOpen = $this->wholeDaysUntil($upcoming->open_date);
            $suggestions[] = [
                'title'    => "{$upcoming->company_name} IPO opens in {$this->dayLabel($daysUntilOpen)}",
                'message'  => "Start preparing funds. Opens {$upcoming->open_date->format('M d')}, minimum: रू " . number_format($upcoming->min_units * $upcoming->price_per_unit),
                'type'     => 'ipo',
                'priority' => 'medium',
                'icon'     => '🔔',
            ];
        }

        return $suggestions;
    }

    /**
     * Analyze spending patterns (PHP-ML or rule-based)
     */
    protected function checkSpendingPatterns(User $user): array
    {
        $suggestions = [];
        $now = Carbon::now();

        // Get last 3 months of category spending
        $categorySpend = Transaction::where('user_id', $user->id)
            ->where('type', 'debit')
            ->whereDate('transaction_date', '>=', $now->copy()->subMonths(3))
            ->with('category')
            ->get()
            ->groupBy('category.name')
            ->map(fn($txs) => $txs->sum('amount'));

        $totalSpend = $categorySpend->sum();

        if ($totalSpend > 0) {
            // Food spending check
            $foodSpend = $categorySpend->get('Food', 0);
            $foodPct   = $foodSpend / $totalSpend;

            if ($foodPct > config('haarray.ml.food_budget_warning', 0.35)) {
                $suggestions[] = [
                    'title'   => 'Food spending is ' . round($foodPct * 100) . '% of total expenses',
                    'message' => 'Your food spending is above the 35% threshold. Consider meal prep 3x/week — estimated monthly savings: रू ' . number_format($foodSpend * 0.15 / 3),
                    'type'    => 'spending',
                    'priority'=> 'medium',
                    'icon'    => '🧠',
                ];
            }

            // Entertainment check
            $entSpend = $categorySpend->get('Entertainment', 0);
            $entPct   = $entSpend / $totalSpend;

            if ($entPct > 0.20) {
                $suggestions[] = [
                    'title'   => 'Entertainment at ' . round($entPct * 100) . '% — review discretionary spend',
                    'message' => 'Consider setting a monthly entertainment budget. Current 3-month avg: रू ' . number_format($entSpend / 3),
                    'type'    => 'spending',
                    'priority'=> 'low',
                    'icon'    => '📊',
                ];
            }

            $monthIncome = Transaction::where('user_id', $user->id)
                ->where('type', 'credit')
                ->whereMonth('transaction_date', $now->month)
                ->sum('amount');
            $monthExpense = Transaction::where('user_id', $user->id)
                ->where('type', 'debit')
                ->whereMonth('transaction_date', $now->month)
                ->sum('amount');
            $savingsRate = $monthIncome > 0 ? (($monthIncome - $monthExpense) / $monthIncome) : 0;

            $profile = $this->advancedML->classifySpendingProfile($foodPct, $entPct, $savingsRate);
            if ($profile['label'] === 'high-risk') {
                $suggestions[] = [
                    'title' => 'ML flagged a high-risk spending profile',
                    'message' => 'Food + discretionary ratios are above your target mix. Risk score: '
                        . round($profile['risk_score'] * 100)
                        . '%. Consider applying a hard weekly cap.',
                    'type' => 'spending',
                    'priority' => 'high',
                    'icon' => '🧪',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Check idle cash vs investment opportunities
     */
    protected function checkIdleCash(User $user): array
    {
        $suggestions = [];
        $threshold = config('haarray.ml.idle_cash_threshold', 5000);

        $idleCash = Account::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('type', ['bank', 'cash', 'wallet', 'esewa', 'khalti'])
            ->sum('balance');

        if ($idleCash > $threshold * 3) {
            $suggestions[] = [
                'title'   => 'रू ' . number_format($idleCash) . ' sitting idle — consider fixed deposit',
                'message' => 'Idle cash above रू ' . number_format($threshold * 3) . ' earns nothing. Even a 30-day FD at current rates (6-7%) would earn ~रू ' . number_format($idleCash * 0.065 / 12) . '/month.',
                'type'    => 'investment',
                'priority'=> 'medium',
                'icon'    => '💡',
            ];
        }

        return $suggestions;
    }

    /**
     * Check savings rate vs target
     */
    protected function checkSavingsRate(User $user): array
    {
        $suggestions = [];
        $now = Carbon::now();

        $income  = Transaction::where('user_id', $user->id)->where('type','credit')
            ->whereMonth('transaction_date', $now->month)->sum('amount');
        $expense = Transaction::where('user_id', $user->id)->where('type','debit')
            ->whereMonth('transaction_date', $now->month)->sum('amount');

        if ($income > 0) {
            $rate   = ($income - $expense) / $income;
            $target = config('haarray.ml.savings_rate_target', 0.30);

            if ($rate < $target) {
                $deficit = round(($target - $rate) * 100, 1);
                $amount  = round($income * ($target - $rate));
                $suggestions[] = [
                    'title'   => "Savings rate {$deficit}% below your 30% target",
                    'message' => "You need to save रू " . number_format($amount) . " more this month to hit 30%. Review discretionary categories.",
                    'type'    => 'savings',
                    'priority'=> 'medium',
                    'icon'    => '🎯',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Convert time delta to whole future days for clean UI text.
     */
    protected function wholeDaysUntil($target): int
    {
        $date = $target instanceof Carbon ? $target : Carbon::parse((string) $target);
        $seconds = now()->diffInSeconds($date, false);

        if ($seconds <= 0) {
            return 0;
        }

        return (int) ceil($seconds / 86400);
    }

    protected function dayLabel(int $days): string
    {
        return $days === 1 ? '1 day' : $days . ' days';
    }

    /**
     * Detect delayed salary, mismatched salary amount, and estimated tax/SSF impact.
     */
    protected function checkSalarySignals(User $user): array
    {
        $suggestions = [];
        $today = Carbon::today();

        $salaryTransactions = Transaction::query()
            ->with('category')
            ->where('user_id', $user->id)
            ->where('type', 'credit')
            ->whereDate('transaction_date', '>=', $today->copy()->subMonths(6)->startOfMonth())
            ->where(function ($query): void {
                $query->whereHas('category', function ($category): void {
                    $category->where('slug', 'salary');
                })->orWhere('title', 'like', '%salary%')
                    ->orWhere('notes', 'like', '%salary%');
            })
            ->orderByDesc('transaction_date')
            ->get();

        if ($salaryTransactions->isEmpty()) {
            return $suggestions;
        }

        $recent = $salaryTransactions->take(4);
        $expectedDay = (int) round($this->median($recent->map(fn (Transaction $tx): int => (int) $tx->transaction_date->day)->all()));
        $expectedDay = max(1, min($expectedDay, 28));
        $expectedNet = (float) $this->median($recent->map(fn (Transaction $tx): float => (float) $tx->amount)->all());

        $currentMonthSalary = $salaryTransactions->first(function (Transaction $tx) use ($today): bool {
            return (int) $tx->transaction_date->year === (int) $today->year
                && (int) $tx->transaction_date->month === (int) $today->month;
        });

        $delayGraceDays = max(0, (int) config('haarray.salary.delay_grace_days', 3));
        $expectedDate = $today->copy()->startOfMonth()->day($expectedDay)->addDays($delayGraceDays);

        if (!$currentMonthSalary && $today->greaterThan($expectedDate)) {
            $lateBy = max(1, $expectedDate->diffInDays($today));
            $suggestions[] = [
                'title' => "Salary delay detected ({$lateBy} day(s) past expected cycle)",
                'message' => 'Expected around day ' . $expectedDay . ' (+' . $delayGraceDays . ' day grace). No salary credit found this month yet.',
                'type' => 'salary',
                'priority' => 'high',
                'icon' => '⏰',
            ];
        }

        if ($currentMonthSalary) {
            $actualNet = (float) $currentMonthSalary->amount;
            $variancePercent = $expectedNet > 0
                ? abs((($actualNet - $expectedNet) / $expectedNet) * 100)
                : 0.0;
            $varianceThreshold = max(1.0, (float) config('haarray.salary.variance_alert_percent', 15.0));

            $taxPercent = max(0, (float) config('haarray.salary.default_tax_percent', 1.0));
            $ssfEnabled = (bool) config('haarray.salary.ssf_enabled_default', false);
            $ssfPercent = $ssfEnabled ? max(0, (float) config('haarray.salary.ssf_employee_percent', 11.0)) : 0.0;
            $deductionPercent = min(95.0, $taxPercent + $ssfPercent);
            $grossEstimate = $deductionPercent >= 99.0
                ? $actualNet
                : round($actualNet / max(0.01, (1 - ($deductionPercent / 100))), 2);

            if ($variancePercent >= $varianceThreshold) {
                $direction = $actualNet >= $expectedNet ? 'higher' : 'lower';
                $suggestions[] = [
                    'title' => 'Salary amount mismatch: ' . round($variancePercent, 1) . '% ' . $direction . ' than recent pattern',
                    'message' => 'Recent median net: रू ' . number_format($expectedNet, 2)
                        . ', this month: रू ' . number_format($actualNet, 2)
                        . '. Estimated gross: रू ' . number_format($grossEstimate, 2)
                        . ' (tax ' . number_format($taxPercent, 2) . '%'
                        . ($ssfEnabled ? ', SSF ' . number_format($ssfPercent, 2) . '%' : '')
                        . ').',
                    'type' => 'salary',
                    'priority' => 'medium',
                    'icon' => '🧾',
                ];
            }

            if ((int) $currentMonthSalary->transaction_date->day > ($expectedDay + $delayGraceDays)) {
                $daysLate = (int) $currentMonthSalary->transaction_date->day - ($expectedDay + $delayGraceDays);
                $suggestions[] = [
                    'title' => 'Salary arrived late this month',
                    'message' => 'Credited on day ' . $currentMonthSalary->transaction_date->day
                        . ' (expected day ' . $expectedDay . ' + ' . $delayGraceDays . ' grace). Late by about ' . $daysLate . ' day(s).',
                    'type' => 'salary',
                    'priority' => 'low',
                    'icon' => '📅',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * @param array<int, float|int> $values
     */
    private function median(array $values): float
    {
        $values = array_values(array_filter(array_map('floatval', $values), fn (float $value): bool => $value > 0));
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return round(($values[$middle - 1] + $values[$middle]) / 2, 2);
    }
}
