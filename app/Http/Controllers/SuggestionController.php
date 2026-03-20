<?php

namespace App\Http\Controllers;

use App\Http\Services\MLSuggestionService;
use App\Models\Account;
use App\Models\GoldPosition;
use App\Models\IPO;
use App\Models\IpoPosition;
use App\Models\Suggestion;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SuggestionController extends Controller
{
    public function __construct(
        private readonly MLSuggestionService $mlSuggestionService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $suggestions = Suggestion::query()
            ->where('user_id', $user->id)
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('suggestions.index', [
            'suggestions' => $suggestions,
        ]);
    }

    public function refresh(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->mlSuggestionService->generateForUser($user);

        return back()->with('success', 'Suggestions refreshed from current finance data.');
    }

    public function markRead(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $suggestion->user_id === (int) $user->id, 403);

        $suggestion->update(['is_read' => true]);

        return back()->with('success', 'Suggestion marked as read.');
    }

    public function clearRead(Request $request): RedirectResponse
    {
        $user = $request->user();

        Suggestion::query()
            ->where('user_id', $user->id)
            ->where('is_read', true)
            ->delete();

        return back()->with('success', 'Read suggestions cleared.');
    }

    public function chat(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $message = trim((string) $validated['message']);
        $messageLower = Str::of($message)->lower()->toString();
        $monthStart = now()->startOfMonth();

        $income = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'credit')
            ->whereDate('transaction_date', '>=', $monthStart)
            ->sum('amount');

        $expense = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'debit')
            ->whereDate('transaction_date', '>=', $monthStart)
            ->sum('amount');

        $idleCash = (float) Account::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->sum('balance');

        $openIpos = IPO::query()
            ->whereIn('status', ['open', 'upcoming'])
            ->orderByRaw("FIELD(status, 'open', 'upcoming')")
            ->orderBy('close_date')
            ->limit(3)
            ->get();

        $ipoValue = (float) IpoPosition::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(units_allotted * COALESCE(current_price, 0)), 0) as total')
            ->value('total');

        $goldValue = (float) GoldPosition::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(grams * COALESCE(current_price_per_gram, buy_price_per_gram)), 0) as total')
            ->value('total');

        $savingsRate = $income > 0
            ? round((($income - $expense) / $income) * 100, 1)
            : 0.0;

        $reply = [];
        $reply[] = 'Here is your latest snapshot:';
        $reply[] = sprintf('- This month income: NPR %s', number_format($income, 2));
        $reply[] = sprintf('- This month expense: NPR %s', number_format($expense, 2));
        $reply[] = sprintf('- Savings rate: %s%%', number_format($savingsRate, 1));
        $reply[] = sprintf('- Current liquid balance: NPR %s', number_format($idleCash, 2));
        $reply[] = sprintf('- Portfolio value (IPO + Gold): NPR %s', number_format($ipoValue + $goldValue, 2));

        if (Str::contains($messageLower, ['ipo', 'allot', 'allotment'])) {
            if ($openIpos->isEmpty()) {
                $reply[] = 'No open/upcoming IPO was found in the current feed.';
            } else {
                $reply[] = 'IPO watchlist from your feed:';
                foreach ($openIpos as $ipo) {
                    $minApply = (float) $ipo->price_per_unit * (int) $ipo->min_units;
                    $status = strtoupper((string) $ipo->status);
                    $closeLabel = $ipo->close_date ? $ipo->close_date->format('Y-m-d') : 'TBD';
                    $afford = $idleCash >= $minApply ? 'Affordable now' : 'Needs more cash';
                    $reply[] = sprintf(
                        '- %s (%s) | Min apply NPR %s | Close %s | %s',
                        (string) $ipo->company_name,
                        $status,
                        number_format($minApply, 2),
                        $closeLabel,
                        $afford
                    );
                }
                $reply[] = 'BOID allotment auto-check is source-limited; keep final allotment status updated after result publish.';
            }
        } elseif (Str::contains($messageLower, ['budget', 'expense', 'save', 'saving'])) {
            $net = $income - $expense;
            if ($net >= 0) {
                $reply[] = sprintf('You are positive this month by NPR %s. Keep at least 20%% of this as reserved cash.', number_format($net, 2));
            } else {
                $reply[] = sprintf('You are overspending by NPR %s this month. Reduce non-essential debits first.', number_format(abs($net), 2));
            }
        } elseif (Str::contains($messageLower, ['gold'])) {
            $reply[] = sprintf('Gold current value in your book is NPR %s.', number_format($goldValue, 2));
            $reply[] = 'Tip: update current per-gram rates after market sync for more accurate gain/loss.';
        } elseif (Str::contains($messageLower, ['bank', 'wallet', 'account', 'balance'])) {
            $activeCount = Account::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->count();
            $reply[] = sprintf('You currently have %d active funding account(s).', $activeCount);
            $reply[] = 'Use quick log for every debit/credit to keep account balances in sync.';
        } else {
            if ($openIpos->isNotEmpty() && $idleCash > 0) {
                $first = $openIpos->first();
                $minApply = (float) $first->price_per_unit * (int) $first->min_units;
                $reply[] = sprintf(
                    'Next actionable item: %s requires about NPR %s minimum.',
                    (string) $first->company_name,
                    number_format($minApply, 2)
                );
            }
            $reply[] = 'Ask me about IPO readiness, budget control, or account planning for specific guidance.';
        }

        return response()->json([
            'reply' => implode(PHP_EOL, $reply),
            'meta' => [
                'idle_cash' => round($idleCash, 2),
                'savings_rate' => round($savingsRate, 1),
            ],
        ]);
    }
}
