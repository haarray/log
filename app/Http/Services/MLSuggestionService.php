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
            ->whereIn('type', ['cash', 'esewa', 'khalti'])
            ->sum('balance');

        $openIPOs = IPO::where('status', 'open')
            ->where('close_date', '>=', now())
            ->get();

        foreach ($openIPOs as $ipo) {
            $minAmount = $ipo->min_units * $ipo->price_per_unit;
            $daysLeft  = now()->diffInDays($ipo->close_date, false);

            if ($idleCash >= $minAmount && $daysLeft >= 0) {
                $suggestions[] = [
                    'title'    => "Apply for {$ipo->company_name} IPO — Closes in {$daysLeft} day(s)",
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
            ->first();

        if ($upcoming) {
            $suggestions[] = [
                'title'    => "{$upcoming->company_name} IPO opens in " . now()->diffInDays($upcoming->open_date) . " day(s)",
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
            ->whereIn('type', ['cash', 'esewa', 'khalti'])
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
}
