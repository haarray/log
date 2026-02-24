<?php

namespace App\Http\Services;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketDataService
{
    private const CACHE_KEY = 'haarray_market_data';
    private const GOLD_LAST_KEY = 'haarray_market_data_gold_last';
    private const DEFAULT_GOLD_TOLA = 142500.0;
    private const DEFAULT_USD_NPR = 133.40;
    private const DEFAULT_NEPSE = 2148.62;

    public function getCached(): array
    {
        $ttl = max(60, (int) config('haarray.market.cache_minutes', 60) * 60);

        return Cache::remember(self::CACHE_KEY, $ttl, function () {
            return $this->fetchAll();
        });
    }

    public function forceRefresh(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->getCached();
    }

    public function fetchAll(): array
    {
        $gold = $this->fetchGold();
        $nepse = $this->fetchNepse();
        $usdNpr = $this->fetchForex();

        $previous = Cache::get(self::CACHE_KEY . ':snapshot');
        $goldChange = $this->percentChange($gold, (float) data_get($previous, 'gold'));
        $nepseChange = $this->percentChange($nepse, (float) data_get($previous, 'nepse'));

        Cache::put(self::CACHE_KEY . ':snapshot', [
            'gold' => $gold,
            'nepse' => $nepse,
            'usd_npr' => $usdNpr,
        ], now()->addDays(2));

        return [
            'gold' => $gold,
            'gold_change' => $goldChange,
            'nepse' => $nepse,
            'nepse_change' => $nepseChange,
            'usd_npr' => $usdNpr,
            'fetched_at' => now()->toDateTimeString(),
        ];
    }

    public function fetchGold(): float
    {
        $fromHamroPatro = $this->fetchGoldFromHamroPatro();
        if ($fromHamroPatro > 0) {
            Cache::put(self::GOLD_LAST_KEY, $fromHamroPatro, now()->addDays(7));
            return $fromHamroPatro;
        }

        $fromGoldPriceNepal = $this->fetchGoldFromGoldPriceNepal();
        if ($fromGoldPriceNepal > 0) {
            Cache::put(self::GOLD_LAST_KEY, $fromGoldPriceNepal, now()->addDays(7));
            return $fromGoldPriceNepal;
        }

        $last = (float) Cache::get(self::GOLD_LAST_KEY, self::DEFAULT_GOLD_TOLA);
        return $last > 0 ? $last : self::DEFAULT_GOLD_TOLA;
    }

    public function fetchForex(): float
    {
        try {
            $nrbUrl = (string) config('haarray.market.nrb_url', '');
            if ($nrbUrl !== '') {
                $response = Http::timeout(10)->get($nrbUrl);
                if ($response->successful()) {
                    $payload = $response->json('data.payload', []);
                    if (is_array($payload)) {
                        foreach ($payload as $dateRow) {
                            foreach ((array) data_get($dateRow, 'rates', []) as $rateRow) {
                                if ((string) data_get($rateRow, 'currency.iso3') === 'USD') {
                                    $sell = (float) data_get($rateRow, 'sell');
                                    if ($sell > 0) {
                                        return $sell;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Forex fetch failed from NRB', ['error' => $exception->getMessage()]);
        }

        try {
            $response = Http::timeout(10)
                ->get((string) config('haarray.market.forex_url', 'https://open.er-api.com/v6/latest/USD'));
            if ($response->successful()) {
                $rate = (float) ($response->json('rates.NPR') ?? 0);
                if ($rate > 0) {
                    return $rate;
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Forex fetch failed from open.er-api', ['error' => $exception->getMessage()]);
        }

        return self::DEFAULT_USD_NPR;
    }

    public function fetchNepse(): float
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders($this->browserHeaders())
                ->get((string) config('haarray.market.nepse_url', 'https://merolagani.com/StockQuote.aspx'));
            if ($response->successful()) {
                $html = $response->body();
                if (preg_match('/NEPSE[^0-9]{0,40}([0-9]{3,4}(?:\.[0-9]{1,2})?)/iu', $html, $matches) === 1) {
                    $value = (float) $matches[1];
                    if ($value > 0) {
                        return $value;
                    }
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('NEPSE scrape failed from merolagani', ['error' => $exception->getMessage()]);
        }

        try {
            $home = Http::timeout(10)
                ->withHeaders($this->browserHeaders())
                ->get($this->sharesansarUrl('/'));
            if ($home->successful()) {
                $html = $home->body();
                if (preg_match('/NEPSE[^0-9]{0,50}([0-9]{3,4}(?:\.[0-9]{1,2})?)/iu', $html, $matches) === 1) {
                    $value = (float) $matches[1];
                    if ($value > 0) {
                        return $value;
                    }
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('NEPSE scrape failed from sharesansar', ['error' => $exception->getMessage()]);
        }

        return self::DEFAULT_NEPSE;
    }

    /**
     * @return array<string, array{symbol:string, company:string, ltp:float, close:float, open:float, volume:float}>
     */
    public function fetchShareSansarTodayPrices(): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders($this->browserHeaders())
                ->get($this->sharesansarUrl((string) config('haarray.market.sources.sharesansar.today_share_path', '/today-share-price')));
            if (!$response->successful()) {
                return [];
            }

            return $this->parseTodayPriceTable($response->body());
        } catch (\Throwable $exception) {
            Log::warning('Unable to fetch ShareSansar today-share-price', ['error' => $exception->getMessage()]);
            return [];
        }
    }

    /**
     * @return array<int, array{
     *   symbol:string,
     *   company_name:string,
     *   units:float,
     *   price_per_unit:float,
     *   open_date:?string,
     *   close_date:?string,
     *   status:string,
     *   source:string
     * }>
     */
    public function fetchIpoIssueRows(): array
    {
        $cdscRows = $this->fetchCdscIssueRows();
        $shareSansarRows = $this->fetchShareSansarIssueRows();

        return $this->mergeIssueRows(array_merge($cdscRows, $shareSansarRows));
    }

    /**
     * @return array<int, array{
     *   symbol:string,
     *   company_name:string,
     *   units:float,
     *   price_per_unit:float,
     *   open_date:?string,
     *   close_date:?string,
     *   status:string,
     *   source:string
     * }>
     */
    public function fetchShareSansarIssueRows(): array
    {
        $rows = [];

        $existingHtml = $this->fetchShareSansarIssueTable((string) config('haarray.market.sources.sharesansar.existing_issue_endpoint', '/existing-issues-hmins'));
        $upcomingHtml = $this->fetchShareSansarIssueTable((string) config('haarray.market.sources.sharesansar.upcoming_issue_endpoint', '/upcoming-issue-hmins'));

        foreach ([$existingHtml, $upcomingHtml] as $html) {
            if ($html === '') {
                continue;
            }
            $rows = array_merge($rows, $this->parseIssueTable($html));
        }

        return $this->mergeIssueRows($rows);
    }

    /**
     * @return array<int, array{
     *   symbol:string,
     *   company_name:string,
     *   units:float,
     *   price_per_unit:float,
     *   open_date:?string,
     *   close_date:?string,
     *   status:string,
     *   source:string
     * }>
     */
    public function fetchCdscIssueRows(): array
    {
        $url = trim((string) config('haarray.market.ipo_url', 'https://cdsc.com.np/cdscportal/IpoList.aspx'));
        if ($url === '') {
            return [];
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders($this->browserHeaders())
                ->get($url);

            if (!$response->successful()) {
                return [];
            }

            return $this->parseCdscIssueTables($response->body());
        } catch (\Throwable $exception) {
            Log::warning('Unable to fetch CDSC IPO list', ['error' => $exception->getMessage()]);
            return [];
        }
    }

    private function fetchGoldFromHamroPatro(): float
    {
        try {
            $url = (string) config('haarray.market.sources.hamropatro.gold_url', 'https://www.hamropatro.com/gold');
            $response = Http::timeout(20)->withHeaders($this->browserHeaders())->get($url);
            if (!$response->successful()) {
                return 0.0;
            }

            $prices = $this->parseHamroPatroGoldList($response->body());
            $value = (float) ($prices['gold_hallmark_tola'] ?? 0);
            return $value > 0 ? $value : 0.0;
        } catch (\Throwable $exception) {
            Log::warning('Gold fetch failed from Hamro Patro', ['error' => $exception->getMessage()]);
            return 0.0;
        }
    }

    private function fetchGoldFromGoldPriceNepal(): float
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders($this->browserHeaders())
                ->get((string) config('haarray.market.gold_url', 'https://www.goldpricenepal.com/'));

            if ($response->successful()) {
                $html = $response->body();
                if (preg_match('/Fine Gold.*?NPR\s*([0-9,]+(?:\.[0-9]+)?)/is', $html, $m) === 1) {
                    return $this->toFloat((string) $m[1]);
                }

                if (preg_match('/Gold Hallmark[^0-9]{0,50}([0-9,]{5,}(?:\.[0-9]+)?)/is', $html, $m) === 1) {
                    return $this->toFloat((string) $m[1]);
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Gold fetch failed from goldpricenepal.com', ['error' => $exception->getMessage()]);
        }

        return 0.0;
    }

    /**
     * @return array<string, float>
     */
    private function parseHamroPatroGoldList(string $html): array
    {
        $rows = [];
        $dom = $this->loadDom($html);
        if (!$dom) {
            return $rows;
        }

        $xpath = new DOMXPath($dom);
        $listNodes = $xpath->query("//ul[contains(@class, 'gold-silver')]/li");
        if (!$listNodes || $listNodes->length < 2) {
            return $rows;
        }

        $items = [];
        foreach ($listNodes as $li) {
            $items[] = trim(preg_replace('/\s+/', ' ', html_entity_decode((string) $li->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        }

        for ($i = 0; $i < count($items) - 1; $i += 2) {
            $label = strtolower(trim((string) ($items[$i] ?? '')));
            $valueText = (string) ($items[$i + 1] ?? '');
            $value = $this->toFloat($valueText);
            if ($value <= 0) {
                continue;
            }

            if (str_contains($label, 'gold hallmark') && str_contains($label, 'tola')) {
                $rows['gold_hallmark_tola'] = $value;
                continue;
            }
            if (str_contains($label, 'gold tajabi') && str_contains($label, 'tola')) {
                $rows['gold_tajabi_tola'] = $value;
                continue;
            }
            if (str_contains($label, 'silver') && str_contains($label, 'tola')) {
                $rows['silver_tola'] = $value;
            }
        }

        return $rows;
    }

    private function fetchShareSansarIssueTable(string $endpointPath, int $type = 1): string
    {
        $endpointPath = trim($endpointPath);
        if ($endpointPath === '') {
            return '';
        }

        try {
            $cookieJar = new CookieJar();

            $home = Http::timeout(20)
                ->withOptions(['cookies' => $cookieJar])
                ->withHeaders($this->browserHeaders())
                ->get($this->sharesansarUrl('/'));
            if (!$home->successful()) {
                return '';
            }

            $token = $this->extractHtmlMetaToken($home->body());
            if ($token === '') {
                return '';
            }

            $response = Http::timeout(20)
                ->withOptions(['cookies' => $cookieJar])
                ->withHeaders(array_merge($this->browserHeaders(), [
                    'X-CSRF-Token' => $token,
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => $this->sharesansarUrl('/'),
                ]))
                ->asForm()
                ->post($this->sharesansarUrl($endpointPath), ['type' => $type]);

            if (!$response->successful()) {
                return '';
            }

            $html = trim($response->body());
            if ($html === '' || str_contains(strtolower($html), 'page expired')) {
                return '';
            }

            return $html;
        } catch (\Throwable $exception) {
            Log::warning('ShareSansar issue endpoint fetch failed', [
                'endpoint' => $endpointPath,
                'error' => $exception->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * @return array<string, array{symbol:string, company:string, ltp:float, close:float, open:float, volume:float}>
     */
    private function parseTodayPriceTable(string $html): array
    {
        $rows = [];
        $dom = $this->loadDom($html);
        if (!$dom) {
            return $rows;
        }

        $xpath = new DOMXPath($dom);
        $trs = $xpath->query("//table[@id='headFixed']//tbody/tr");
        if (!$trs) {
            return $rows;
        }

        foreach ($trs as $tr) {
            $symbolRaw = (string) $xpath->evaluate("normalize-space(string(./td[2]))", $tr);
            $symbol = $this->normalizeSymbol($symbolRaw);
            if ($symbol === '') {
                continue;
            }

            $company = trim((string) $xpath->evaluate("normalize-space(string(./td[2]//a/@title))", $tr));
            $ltp = $this->toFloat((string) $xpath->evaluate("normalize-space(string(./td[8]))", $tr));
            if ($ltp <= 0) {
                continue;
            }

            $rows[$symbol] = [
                'symbol' => $symbol,
                'company' => $company !== '' ? $company : $symbol,
                'open' => $this->toFloat((string) $xpath->evaluate("normalize-space(string(./td[4]))", $tr)),
                'close' => $this->toFloat((string) $xpath->evaluate("normalize-space(string(./td[7]))", $tr)),
                'ltp' => $ltp,
                'volume' => $this->toFloat((string) $xpath->evaluate("normalize-space(string(./td[12]))", $tr)),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{
     *   symbol:string,
     *   company_name:string,
     *   units:float,
     *   price_per_unit:float,
     *   open_date:?string,
     *   close_date:?string,
     *   status:string,
     *   source:string
     * }>
     */
    private function parseIssueTable(string $html): array
    {
        $rows = [];
        $dom = $this->loadDom($html);
        if (!$dom) {
            return $rows;
        }

        $xpath = new DOMXPath($dom);
        $trs = $xpath->query('//table//tbody/tr');
        if (!$trs) {
            return $rows;
        }

        foreach ($trs as $tr) {
            $symbol = $this->normalizeSymbol((string) $xpath->evaluate("normalize-space(string(./td[2]))", $tr));
            $companyName = trim((string) $xpath->evaluate("normalize-space(string(./td[3]))", $tr));

            if ($symbol === '' && $companyName === '') {
                continue;
            }

            $statusText = trim((string) $xpath->evaluate("normalize-space(string(./td[8]))", $tr));
            $openDate = $this->normalizeIssueDate((string) $xpath->evaluate("normalize-space(string(./td[6]))", $tr));
            $closeDate = $this->normalizeIssueDate((string) $xpath->evaluate("normalize-space(string(./td[7]))", $tr));

            $rows[] = [
                'symbol' => $symbol,
                'company_name' => $companyName !== '' ? $companyName : $symbol,
                'units' => $this->toFloat((string) $xpath->evaluate("normalize-space(string(./td[4]))", $tr)),
                'price_per_unit' => $this->toFloat((string) $xpath->evaluate("normalize-space(string(./td[5]))", $tr)),
                'open_date' => $openDate,
                'close_date' => $closeDate,
                'status' => $this->normalizeIssueStatus($statusText, $openDate, $closeDate),
                'source' => 'sharesansar',
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{
     *   symbol:string,
     *   company_name:string,
     *   units:float,
     *   price_per_unit:float,
     *   open_date:?string,
     *   close_date:?string,
     *   status:string,
     *   source:string
     * }>
     */
    private function parseCdscIssueTables(string $html): array
    {
        $rows = [];
        $dom = $this->loadDom($html);
        if (!$dom) {
            return $rows;
        }

        $xpath = new DOMXPath($dom);
        $tables = $xpath->query('//table');
        if (!$tables) {
            return $rows;
        }

        foreach ($tables as $table) {
            $headerNodes = $xpath->query('.//tr[1]/th | .//tr[1]/td', $table);
            if (!$headerNodes || $headerNodes->length === 0) {
                continue;
            }

            $headers = [];
            foreach ($headerNodes as $index => $node) {
                $headers[$index] = strtolower(trim(preg_replace('/\s+/', ' ', (string) $node->textContent) ?: ''));
            }

            $companyIndex = $this->findHeaderIndex($headers, ['company', 'issue', 'name']);
            $symbolIndex = $this->findHeaderIndex($headers, ['symbol', 'script', 'code']);
            $openIndex = $this->findHeaderIndex($headers, ['open']);
            $closeIndex = $this->findHeaderIndex($headers, ['close']);
            $priceIndex = $this->findHeaderIndex($headers, ['price', 'rate']);
            $unitsIndex = $this->findHeaderIndex($headers, ['unit', 'kitta', 'quantity']);
            $statusIndex = $this->findHeaderIndex($headers, ['status']);

            if ($companyIndex < 0 && $symbolIndex < 0) {
                continue;
            }

            $rowNodes = $xpath->query('.//tr[position()>1]', $table);
            if (!$rowNodes) {
                continue;
            }

            foreach ($rowNodes as $rowNode) {
                $cellNodes = $xpath->query('./td | ./th', $rowNode);
                if (!$cellNodes || $cellNodes->length < 2) {
                    continue;
                }

                $cellValue = static function ($cells, int $index): string {
                    if ($index < 0 || $index >= $cells->length) {
                        return '';
                    }
                    return trim(preg_replace('/\s+/', ' ', (string) $cells->item($index)?->textContent) ?: '');
                };

                $companyRaw = $cellValue($cellNodes, $companyIndex);
                $symbolRaw = $cellValue($cellNodes, $symbolIndex);
                $openDate = $this->normalizeIssueDate($cellValue($cellNodes, $openIndex));
                $closeDate = $this->normalizeIssueDate($cellValue($cellNodes, $closeIndex));
                $statusText = $cellValue($cellNodes, $statusIndex);

                $symbol = $this->normalizeSymbol($symbolRaw);
                if ($symbol === '' && $companyRaw !== '') {
                    $symbol = $this->extractSymbolFromText($companyRaw);
                }

                $company = trim($companyRaw);
                if ($company === '') {
                    $company = $symbol !== '' ? $symbol : 'Unknown IPO';
                }

                if ($company === 'Unknown IPO' && $symbol === '') {
                    continue;
                }

                $price = $this->toFloat($cellValue($cellNodes, $priceIndex));
                if ($price <= 0) {
                    $price = 100.0;
                }

                $units = $this->toFloat($cellValue($cellNodes, $unitsIndex));
                if ($units <= 0) {
                    $units = (float) max(1, (int) config('haarray.ipo.min_application', 10));
                }

                $rows[] = [
                    'symbol' => $symbol,
                    'company_name' => $company,
                    'units' => $units,
                    'price_per_unit' => $price,
                    'open_date' => $openDate,
                    'close_date' => $closeDate,
                    'status' => $this->normalizeIssueStatus($statusText, $openDate, $closeDate),
                    'source' => 'cdsc',
                ];
            }
        }

        return $this->mergeIssueRows($rows);
    }

    private function normalizeIssueStatus(string $statusText, ?string $openDate, ?string $closeDate): string
    {
        $status = strtolower(trim($statusText));
        if (str_contains($status, 'open')) {
            return 'open';
        }
        if (str_contains($status, 'close')) {
            return 'closed';
        }
        if (str_contains($status, 'coming') || str_contains($status, 'soon')) {
            return 'upcoming';
        }

        $today = Carbon::today();
        $open = $openDate ? Carbon::parse($openDate) : null;
        $close = $closeDate ? Carbon::parse($closeDate) : null;

        if ($open && $today->lt($open)) {
            return 'upcoming';
        }
        if ($close && $today->gt($close)) {
            return 'closed';
        }
        if ($open && $close && $today->betweenIncluded($open, $close)) {
            return 'open';
        }

        return 'upcoming';
    }

    private function normalizeIssueDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/coming\s+soon/i', $value) === 1) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/\d{4}\/\d{2}\/\d{2}/', $value, $match) === 1) {
            try {
                return Carbon::createFromFormat('Y/m/d', (string) $match[0])->format('Y-m-d');
            } catch (\Throwable) {
                // Continue trying other formats.
            }
        }

        if (preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}/', $value, $match) === 1) {
            foreach (['d/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, (string) $match[0])->format('Y-m-d');
                } catch (\Throwable) {
                    // Continue trying.
                }
            }
        }

        if (preg_match('/\d{1,2}\s+[A-Za-z]{3,9}\s*,?\s*\d{4}/', $value, $match) === 1) {
            try {
                return Carbon::parse((string) $match[0])->format('Y-m-d');
            } catch (\Throwable) {
                // Continue trying.
            }
        }

        return null;
    }

    /**
     * @param array<int, array{
     *   symbol?:string,
     *   company_name?:string,
     *   units?:float,
     *   price_per_unit?:float,
     *   open_date?:?string,
     *   close_date?:?string,
     *   status?:string,
     *   source?:string
     * }> $rows
     * @return array<int, array{
     *   symbol:string,
     *   company_name:string,
     *   units:float,
     *   price_per_unit:float,
     *   open_date:?string,
     *   close_date:?string,
     *   status:string,
     *   source:string
     * }>
     */
    private function mergeIssueRows(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $key = strtoupper(trim((string) ($row['symbol'] ?? ''))) . '|' . strtolower(trim((string) ($row['company_name'] ?? '')));
            if ($key === '|') {
                continue;
            }

            if (!isset($merged[$key])) {
                $merged[$key] = $row;
                continue;
            }

            $currentScore = $this->issueRowScore($merged[$key]) + $this->issueSourcePriority((string) ($merged[$key]['source'] ?? ''));
            $candidateScore = $this->issueRowScore($row) + $this->issueSourcePriority((string) ($row['source'] ?? ''));
            if ($candidateScore >= $currentScore) {
                $merged[$key] = $row;
            }
        }

        return array_values($merged);
    }

    /**
     * @param array{
     *   symbol?:string,
     *   company_name?:string,
     *   units?:float,
     *   price_per_unit?:float,
     *   open_date?:?string,
     *   close_date?:?string,
     *   status?:string
     * } $row
     */
    private function issueRowScore(array $row): int
    {
        $score = 0;
        if (trim((string) ($row['symbol'] ?? '')) !== '') {
            $score += 2;
        }
        if ((float) ($row['units'] ?? 0) > 0) {
            $score += 1;
        }
        if ((float) ($row['price_per_unit'] ?? 0) > 0) {
            $score += 1;
        }
        if (!empty($row['open_date'])) {
            $score += 2;
        }
        if (!empty($row['close_date'])) {
            $score += 2;
        }
        if (in_array((string) ($row['status'] ?? ''), ['open', 'upcoming', 'closed'], true)) {
            $score += 2;
        }

        return $score;
    }

    private function issueSourcePriority(string $source): int
    {
        $source = strtolower(trim($source));

        return match ($source) {
            'cdsc' => 3,
            'sharesansar' => 2,
            default => 1,
        };
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $keywords
     */
    private function findHeaderIndex(array $headers, array $keywords): int
    {
        foreach ($headers as $index => $header) {
            foreach ($keywords as $keyword) {
                if (str_contains($header, strtolower($keyword))) {
                    return (int) $index;
                }
            }
        }

        return -1;
    }

    private function extractSymbolFromText(string $value): string
    {
        $upper = strtoupper($value);

        if (preg_match('/\(([A-Z0-9\.\-]{2,12})\)/', $upper, $match) === 1) {
            return $this->normalizeSymbol((string) ($match[1] ?? ''));
        }

        if (preg_match_all('/\b([A-Z]{2,12})\b/', $upper, $matches) >= 1) {
            $ignore = ['IPO', 'CDSC', 'NPR', 'NEPSE'];
            foreach ((array) ($matches[1] ?? []) as $symbol) {
                if (!in_array($symbol, $ignore, true)) {
                    return $this->normalizeSymbol((string) $symbol);
                }
            }
        }

        return '';
    }

    private function sharesansarUrl(string $path): string
    {
        $base = rtrim((string) config('haarray.market.sources.sharesansar.base_url', 'https://www.sharesansar.com'), '/');
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function browserHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Connection' => 'keep-alive',
        ];
    }

    private function extractHtmlMetaToken(string $html): string
    {
        if (preg_match('/<meta\s+name="_token"\s+content="([^"]+)"/i', $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function normalizeSymbol(string $value): string
    {
        $symbol = strtoupper(trim($value));
        $symbol = preg_replace('/[^A-Z0-9\.\-]/', '', $symbol) ?: '';
        return $symbol;
    }

    private function toFloat(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        if (preg_match('/-?\d[\d,]*(?:\.\d+)?/', $value, $matches) !== 1) {
            return 0.0;
        }

        $normalized = str_replace(',', '', (string) ($matches[0] ?? ''));
        if ($normalized === '' || !is_numeric($normalized)) {
            return 0.0;
        }

        return (float) $normalized;
    }

    private function percentChange(float $current, float $previous): float
    {
        if ($current <= 0 || $previous <= 0) {
            return 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function loadDom(string $html): ?DOMDocument
    {
        if (trim($html) === '') {
            return null;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (!$loaded) {
            return null;
        }

        return $dom;
    }
}
