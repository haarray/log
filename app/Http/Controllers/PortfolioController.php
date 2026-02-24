<?php

namespace App\Http\Controllers;

use App\Http\Services\MarketSyncService;
use App\Models\GoldPosition;
use App\Models\IPO;
use App\Models\IpoPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $ipos = IPO::query()
            ->orderByRaw("FIELD(status, 'open', 'upcoming', 'closed')")
            ->orderBy('close_date')
            ->get();

        $ipoPositions = IpoPosition::query()
            ->with('ipo')
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        $goldPositions = GoldPosition::query()
            ->where('user_id', $user->id)
            ->latest('bought_at')
            ->latest('id')
            ->get();

        $summary = [
            'ipo_invested' => (float) $ipoPositions->sum('invested_amount'),
            'ipo_current' => (float) $ipoPositions->sum(function (IpoPosition $position): float {
                return max((int) $position->units_allotted, 0) * (float) ($position->current_price ?? 0);
            }),
            'gold_invested' => (float) $goldPositions->sum(fn (GoldPosition $position): float => $position->investedAmount()),
            'gold_current' => (float) $goldPositions->sum(fn (GoldPosition $position): float => $position->currentValue()),
        ];

        return view('portfolio.index', [
            'ipos' => $ipos,
            'ipoPositions' => $ipoPositions,
            'goldPositions' => $goldPositions,
            'summary' => $summary,
        ]);
    }

    public function storeIpo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'symbol' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'in:open,upcoming,closed'],
            'open_date' => ['nullable', 'date'],
            'close_date' => ['nullable', 'date'],
            'price_per_unit' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'min_units' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_units' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'listing_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        IPO::query()->create([
            'company_name' => trim((string) $validated['company_name']),
            'symbol' => strtoupper(trim((string) ($validated['symbol'] ?? ''))) ?: null,
            'status' => (string) $validated['status'],
            'open_date' => $validated['open_date'] ?? null,
            'close_date' => $validated['close_date'] ?? null,
            'price_per_unit' => (float) $validated['price_per_unit'],
            'min_units' => (int) $validated['min_units'],
            'max_units' => isset($validated['max_units']) ? (int) $validated['max_units'] : null,
            'listing_date' => $validated['listing_date'] ?? null,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ]);

        return back()->with('success', 'IPO master entry created.');
    }

    public function syncMarket(Request $request, MarketSyncService $marketSync): RedirectResponse
    {
        $report = $marketSync->sync(true);

        return back()->with('success', sprintf(
            'Market synced. IPOs +%d/%d, prices %d, gold updated %d, alerts %d.',
            (int) $report['ipos_created'],
            (int) $report['ipos_updated'],
            (int) $report['ipo_prices_updated'],
            (int) $report['gold_positions_updated'],
            (int) $report['alerts_sent']
        ));
    }

    public function storeIpoPosition(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'ipo_id' => ['required', 'integer', 'exists:ipos,id'],
            'status' => ['required', 'in:applied,allotted,sold,cancelled'],
            'units_applied' => ['required', 'integer', 'min:0', 'max:1000000'],
            'units_allotted' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'sold_units' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'invested_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'current_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'sold_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'applied_at' => ['nullable', 'date'],
            'sold_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        IpoPosition::query()->create([
            'user_id' => $user->id,
            'ipo_id' => (int) $validated['ipo_id'],
            'status' => (string) $validated['status'],
            'units_applied' => (int) $validated['units_applied'],
            'units_allotted' => (int) ($validated['units_allotted'] ?? 0),
            'sold_units' => (int) ($validated['sold_units'] ?? 0),
            'invested_amount' => (float) $validated['invested_amount'],
            'current_price' => isset($validated['current_price']) ? (float) $validated['current_price'] : null,
            'sold_amount' => isset($validated['sold_amount']) ? (float) $validated['sold_amount'] : null,
            'applied_at' => $validated['applied_at'] ?? null,
            'sold_at' => $validated['sold_at'] ?? null,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ]);

        return back()->with('success', 'IPO position saved.');
    }

    public function updateIpoPosition(Request $request, IpoPosition $position): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $position->user_id === (int) $user->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:applied,allotted,sold,cancelled'],
            'units_applied' => ['required', 'integer', 'min:0', 'max:1000000'],
            'units_allotted' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'sold_units' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'invested_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'current_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'sold_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'applied_at' => ['nullable', 'date'],
            'sold_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $position->update([
            'status' => (string) $validated['status'],
            'units_applied' => (int) $validated['units_applied'],
            'units_allotted' => (int) ($validated['units_allotted'] ?? 0),
            'sold_units' => (int) ($validated['sold_units'] ?? 0),
            'invested_amount' => (float) $validated['invested_amount'],
            'current_price' => isset($validated['current_price']) ? (float) $validated['current_price'] : null,
            'sold_amount' => isset($validated['sold_amount']) ? (float) $validated['sold_amount'] : null,
            'applied_at' => $validated['applied_at'] ?? null,
            'sold_at' => $validated['sold_at'] ?? null,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ]);

        return back()->with('success', 'IPO position updated.');
    }

    public function deleteIpoPosition(Request $request, IpoPosition $position): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $position->user_id === (int) $user->id, 403);

        $position->delete();

        return back()->with('success', 'IPO position deleted.');
    }

    public function storeGold(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'grams' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'buy_price_per_gram' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'current_price_per_gram' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'bought_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        GoldPosition::query()->create([
            'user_id' => $user->id,
            'label' => trim((string) ($validated['label'] ?? '')) ?: null,
            'source' => trim((string) ($validated['source'] ?? '')) ?: null,
            'grams' => (float) $validated['grams'],
            'buy_price_per_gram' => (float) $validated['buy_price_per_gram'],
            'current_price_per_gram' => isset($validated['current_price_per_gram']) ? (float) $validated['current_price_per_gram'] : null,
            'bought_at' => $validated['bought_at'] ?? null,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ]);

        return back()->with('success', 'Gold position added.');
    }

    public function updateGold(Request $request, GoldPosition $gold): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $gold->user_id === (int) $user->id, 403);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'grams' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'buy_price_per_gram' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'current_price_per_gram' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'bought_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $gold->update([
            'label' => trim((string) ($validated['label'] ?? '')) ?: null,
            'source' => trim((string) ($validated['source'] ?? '')) ?: null,
            'grams' => (float) $validated['grams'],
            'buy_price_per_gram' => (float) $validated['buy_price_per_gram'],
            'current_price_per_gram' => isset($validated['current_price_per_gram']) ? (float) $validated['current_price_per_gram'] : null,
            'bought_at' => $validated['bought_at'] ?? null,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ]);

        return back()->with('success', 'Gold position updated.');
    }

    public function deleteGold(Request $request, GoldPosition $gold): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $gold->user_id === (int) $user->id, 403);

        $gold->delete();

        return back()->with('success', 'Gold position deleted.');
    }
}
