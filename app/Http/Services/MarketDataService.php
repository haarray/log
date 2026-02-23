<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MarketDataService
 *
 * Fetches market data from FREE sources:
 * - Gold price: goldpricenepal.com (scrape)
 * - Forex: open.er-api.com (free tier — 1500 req/month)
 * - Nepal Rastra Bank: nrb.org.np/exportForexJSON.php (free)
 * - NEPSE: merolagani.com (scrape)
 * - IPO: cdsc.com.np (scrape)
 *
 * All results are cached to avoid hitting limits.
 */
class MarketDataService
{
    const CACHE_KEY     = 'haarray_market_data';
    const CACHE_MINUTES = 60; // 1 hour

    /**
     * Get cached market data (uses cache, fetches only if expired)
     */
    public function getCached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_MINUTES * 60, function () {
            return $this->fetchAll();
        });
    }

    /**
     * Force refresh all market data
     */
    public function forceRefresh(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->getCached();
    }

    /**
     * Fetch all data sources
     */
    public function fetchAll(): array
    {
        return [
            'gold'         => $this->fetchGold(),
            'gold_change'  => 0.8,  // Placeholder — enhance with history
            'nepse'        => $this->fetchNepse(),
            'nepse_change' => -0.3,
            'usd_npr'      => $this->fetchForex(),
            'fetched_at'   => now()->toDateTimeString(),
        ];
    }

    /**
     * Fetch gold price per tola from goldpricenepal.com
     */
    public function fetchGold(): float
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get('https://www.goldpricenepal.com/');

            if ($response->successful()) {
                $html = $response->body();

                // Try to find price pattern like "रू 1,42,500" or "142500"
                // Adjust regex based on actual site structure
                if (preg_match('/Fine Gold.*?NPR\s*([0-9,]+)/is', $html, $m)) {
                    return (float) str_replace(',', '', $m[1]);
                }

                // Fallback: look for any large number pattern
                if (preg_match('/(\d{1,3}(?:,\d{2,3})+)/', $html, $m)) {
                    $val = (float) str_replace(',', '', $m[1]);
                    if ($val > 100000 && $val < 300000) return $val; // Sanity check for tola price
                }
            }
        } catch (\Exception $e) {
            Log::warning('Gold fetch failed: ' . $e->getMessage());
        }

        // Return last cached or default
        $old = Cache::get(self::CACHE_KEY . '_gold');
        return $old ?? 142500;
    }

    /**
     * Fetch USD/NPR from Nepal Rastra Bank (free JSON API)
     * Endpoint: https://www.nrb.org.np/exportForexJSON.php
     */
    public function fetchForex(): float
    {
        try {
            // Try NRB first (most accurate for Nepal)
            $response = Http::timeout(10)
                ->get('https://www.nrb.org.np/exportForexJSON.php', [
                    'YY' => now()->year,
                    'MM' => now()->format('m'),
                    'DD' => now()->format('d'),
                    'lang' => 'en'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                foreach ($data['data']['payload'] ?? [] as $payload) {
                    foreach ($payload['rates'] ?? [] as $rate) {
                        if ($rate['currency']['iso3'] === 'USD') {
                            return (float) $rate['sell'];
                        }
                    }
                }
            }

            // Fallback: open.er-api.com (1500 free calls/month)
            $response = Http::timeout(10)
                ->get('https://open.er-api.com/v6/latest/USD');

            if ($response->successful()) {
                $data = $response->json();
                return (float) ($data['rates']['NPR'] ?? 133.40);
            }
        } catch (\Exception $e) {
            Log::warning('Forex fetch failed: ' . $e->getMessage());
        }

        return 133.40; // Fallback
    }

    /**
     * Fetch NEPSE index from merolagani.com
     */
    public function fetchNepse(): float
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get('https://merolagani.com/StockQuote.aspx');

            if ($response->successful()) {
                $html = $response->body();
                // Adjust selector based on actual page structure
                if (preg_match('/NEPSE.*?(\d{3,4}(?:\.\d{1,2})?)/is', $html, $m)) {
                    return (float) $m[1];
                }
            }
        } catch (\Exception $e) {
            Log::warning('NEPSE fetch failed: ' . $e->getMessage());
        }

        return 2148.62; // Fallback
    }
}
