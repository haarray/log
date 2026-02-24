<?php

namespace App\Http\Services;

use App\Models\Account;
use App\Models\GoldPosition;
use App\Models\IPO;
use App\Models\IpoPosition;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Support\Facades\Cache;

class MarketSyncService
{
    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @return array{
     *   issues_seen:int,
     *   ipos_created:int,
     *   ipos_updated:int,
     *   prices_seen:int,
     *   ipo_prices_updated:int,
     *   ipo_positions_updated:int,
     *   gold_positions_updated:int,
     *   gold_per_tola:float,
     *   gold_per_gram:float,
     *   alerts_sent:int
     * }
     */
    public function sync(bool $notify = false): array
    {
        $report = [
            'issues_seen' => 0,
            'ipos_created' => 0,
            'ipos_updated' => 0,
            'prices_seen' => 0,
            'ipo_prices_updated' => 0,
            'ipo_positions_updated' => 0,
            'gold_positions_updated' => 0,
            'gold_per_tola' => 0.0,
            'gold_per_gram' => 0.0,
            'alerts_sent' => 0,
        ];

        $issueRows = $this->marketData->fetchIpoIssueRows();
        $report['issues_seen'] = count($issueRows);

        foreach ($issueRows as $row) {
            $symbol = strtoupper(trim((string) ($row['symbol'] ?? '')));
            $companyName = trim((string) ($row['company_name'] ?? ''));
            if ($symbol === '' && $companyName === '') {
                continue;
            }

            $query = IPO::query();
            if ($symbol !== '') {
                $query->where('symbol', $symbol);
            } else {
                $query->whereRaw('LOWER(company_name) = ?', [strtolower($companyName)]);
            }

            /** @var IPO|null $ipo */
            $ipo = $query->first();

            $payload = [
                'symbol' => $symbol !== '' ? $symbol : null,
                'company_name' => $companyName !== '' ? $companyName : ($symbol !== '' ? $symbol : 'Unknown IPO'),
                'status' => $this->normalizeStatus((string) ($row['status'] ?? 'upcoming')),
                'open_date' => $row['open_date'] ?? null,
                'close_date' => $row['close_date'] ?? null,
                'price_per_unit' => (float) ($row['price_per_unit'] ?? 0) > 0
                    ? (float) $row['price_per_unit']
                    : 100.0,
                'min_units' => $ipo?->min_units ?: (int) config('haarray.ipo.min_application', 10),
                'max_units' => $ipo?->max_units,
                'listing_date' => $ipo?->listing_date,
                'notes' => $this->mergeNotes((string) ($ipo?->notes ?? ''), 'Synced from ShareSansar issue board'),
            ];

            if (!$ipo) {
                IPO::query()->create($payload);
                $report['ipos_created']++;
                continue;
            }

            $dirty = false;
            foreach ($payload as $key => $value) {
                if ($ipo->getAttribute($key) != $value) {
                    $ipo->setAttribute($key, $value);
                    $dirty = true;
                }
            }

            if ($dirty) {
                $ipo->save();
                $report['ipos_updated']++;
            }
        }

        $priceRows = $this->marketData->fetchShareSansarTodayPrices();
        $report['prices_seen'] = count($priceRows);

        $ipos = IPO::query()
            ->whereNotNull('symbol')
            ->get();

        foreach ($ipos as $ipo) {
            $symbol = strtoupper(trim((string) $ipo->symbol));
            if ($symbol === '' || !isset($priceRows[$symbol])) {
                continue;
            }

            $price = (float) ($priceRows[$symbol]['ltp'] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $changed = false;
            if ((float) ($ipo->market_price ?? 0) !== $price) {
                $ipo->market_price = $price;
                $changed = true;
            }

            $ipo->market_price_updated_at = now();
            if ($changed) {
                $report['ipo_prices_updated']++;
            }

            if ($ipo->isDirty()) {
                $ipo->save();
            }
        }

        $activePositions = IpoPosition::query()
            ->with('ipo')
            ->whereIn('status', ['applied', 'allotted'])
            ->get();

        foreach ($activePositions as $position) {
            $marketPrice = (float) ($position->ipo?->market_price ?? 0);
            if ($marketPrice <= 0) {
                continue;
            }

            if ((float) ($position->current_price ?? 0) === $marketPrice) {
                continue;
            }

            $position->current_price = $marketPrice;
            $position->save();
            $report['ipo_positions_updated']++;
        }

        $goldPerTola = (float) $this->marketData->fetchGold();
        $report['gold_per_tola'] = $goldPerTola;
        if ($goldPerTola > 0) {
            $goldPerGram = round($goldPerTola / 11.664, 2);
            $report['gold_per_gram'] = $goldPerGram;

            if ($goldPerGram > 0) {
                $report['gold_positions_updated'] = GoldPosition::query()->update([
                    'current_price_per_gram' => $goldPerGram,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($notify) {
            $report['alerts_sent'] = $this->sendIpoOpportunityAlerts();
        }

        // Keep dashboard ticker aligned with latest sync state.
        $this->marketData->forceRefresh();

        return $report;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (in_array($status, ['open', 'upcoming', 'closed'], true)) {
            return $status;
        }

        return 'upcoming';
    }

    private function mergeNotes(string $notes, string $line): string
    {
        $notes = trim($notes);
        if ($notes === '') {
            return $line;
        }
        if (str_contains($notes, $line)) {
            return $notes;
        }

        return $notes . PHP_EOL . $line;
    }

    private function sendIpoOpportunityAlerts(): int
    {
        $openIpos = IPO::query()
            ->where('status', 'open')
            ->where(function ($query): void {
                $query->whereNull('close_date')
                    ->orWhereDate('close_date', '>=', now()->toDateString());
            })
            ->get();

        if ($openIpos->isEmpty()) {
            return 0;
        }

        $users = User::query()->get();
        $sent = 0;

        foreach ($users as $user) {
            $availableCash = (float) Account::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->sum('balance');

            foreach ($openIpos as $ipo) {
                $minUnits = max((int) ($ipo->min_units ?: config('haarray.ipo.min_application', 10)), 1);
                $required = $minUnits * (float) ($ipo->price_per_unit ?: 0);
                if ($required <= 0 || $availableCash < $required) {
                    continue;
                }

                $cacheKey = sprintf('log:ipo-alert:%d:%d:%s', (int) $user->id, (int) $ipo->id, now()->format('Y-m-d-H'));
                if (Cache::has($cacheKey)) {
                    continue;
                }

                $close = $ipo->close_date ? $ipo->close_date->format('Y-m-d') : 'TBD';
                $title = 'IPO Opportunity: ' . (string) $ipo->company_name;
                $message = sprintf(
                    'You have NPR %s available. Minimum required for %s is NPR %s (%d units). Closing: %s.',
                    number_format($availableCash, 2),
                    (string) $ipo->symbol ?: (string) $ipo->company_name,
                    number_format($required, 2),
                    $minUnits,
                    $close
                );

                $result = $this->notifier->toUser($user, $title, $message, [
                    'channels' => ['in_app', 'telegram'],
                    'level' => 'info',
                    'url' => '/portfolio',
                ]);

                if (((int) ($result['in_app'] ?? 0) + (int) ($result['telegram'] ?? 0)) > 0) {
                    $sent++;
                }

                Cache::put($cacheKey, true, now()->addHours(4));
            }
        }

        return $sent;
    }
}
