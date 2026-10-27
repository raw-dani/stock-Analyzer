<?php

declare(strict_types=1);

namespace app\services\market;

use app\models\Stock;
use Yii;
use yii\base\Exception;

/**
 * Yahoo Finance provider — query1.finance.yahoo.com (task 2.3).
 *
 * Yahoo Finance tidak memerlukan API key. Data harian diambil via
 * endpoint chart v8 yang GRATIS tanpa autentikasi:
 *
 *   https://query1.finance.yahoo.com/v8/finance/chart/{symbol}
 *   ?period1=<unix_ts>&period2=<unix_ts>&interval=1d
 *
 * Rate limit: ~2000 request/10 menit per IP.
 *
 * Stock list: daftar statis popular stocks (bisa diperluas) + auto-register
 * via `php yii data/sync <symbol>`.
 */
final class YahooFinanceProvider implements DataProviderInterface, IntradayDataProviderInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const CHART_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';

    public function __construct(
        public string $baseUrl = 'https://query1.finance.yahoo.com',
        public int $rateLimitPerMinute = 30,
    ) {
    }

    private array $timestamps = [];

    /**
     * Ambil bar OHLCV harian via chart endpoint (tanpa API key / crumb).
     */
    public function getDailyBars(string $symbol, ?string $fromDate = null, ?string $toDate = null): array
    {
        return $this->fetchChartBars($symbol, '1d', $fromDate ? strtotime($fromDate) : strtotime('-2 years'), $toDate ? strtotime($toDate) : time(), 'Y-m-d');
    }

    /**
     * Ambil bar OHLCV intraday via chart endpoint (tanpa API key).
     * Interval didukung: '1h'. '2h'/'4h' diagregasi oleh service dari 1h.
     *
     * Yahoo membatasi histori 1h (~730 hari). Rentang panjang dipecah
     * menjadi chunk 60 hari agar tidak terpotong diam-diam.
     *
     * @return IntradayBar[]
     */
    public function getIntradayBars(string $symbol, string $interval = '1h', ?string $fromDatetime = null, ?string $toDatetime = null): array
    {
        $interval = strtolower(trim($interval));
        if (!in_array($interval, ['1h'], true)) {
            throw new Exception("Yahoo Finance: interval {$interval} tidak didukung langsung (gunakan 1h).");
        }
        // Default: 60 hari terakhir (cukup untuk membentuk ~90 bar 4H / ~180 bar 2H).
        $toTs = $toDatetime ? strtotime($toDatetime) : time();
        $fromTs = $fromDatetime ? strtotime($fromDatetime) : strtotime('-60 days', $toTs);

        $all = [];
        // Pecah per 60 hari agar tiap request di bawah batas bar Yahoo.
        $chunk = 60 * 86400;
        for ($start = $fromTs; $start < $toTs; $start += $chunk) {
            $end = min($start + $chunk, $toTs);
            $bars = $this->fetchIntradayChunk($symbol, $start, $end);
            foreach ($bars as $b) {
                $all[$b->datetime] = $b; // dedup antar chunk
            }
        }
        ksort($all);
        return array_values($all);
    }

    /** @return IntradayBar[] */
    private function fetchIntradayChunk(string $symbol, int $fromTs, int $toTs): array
    {
        $fromTs = max($fromTs, (int) strtotime('1970-01-01'));
        $this->rateLimit();

        $url = self::CHART_URL . urlencode(strtoupper(str_replace('.', '-', $symbol)))
            . '?period1=' . $fromTs . '&period2=' . $toTs . '&interval=1h&includePrePost=false';

        $body = $this->curlGet($url);
        $data = json_decode($body, true);

        if (!isset($data['chart']['result'][0])) {
            $err = $data['chart']['error'] ?? null;
            // 429 / simbol kosong: kembalikan chunk kosong agar sync simbol
            // lain tetap jalan (caller mencatat warning).
            if (($err['code'] ?? '') === 'Not Found') {
                return [];
            }
            throw new Exception('Yahoo Finance: error for ' . $symbol . ($err ? ' - ' . ($err['code'] ?? '') : ''));
        }

        $result = $data['chart']['result'][0];
        $gmtoffset = (int) ($result['meta']['gmtoffset'] ?? 0);
        $timestamps = $result['timestamp'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? [];

        $opens = $quote['open'] ?? [];
        $highs = $quote['high'] ?? [];
        $lows = $quote['low'] ?? [];
        $closes = $quote['close'] ?? [];
        $volumes = $quote['volume'] ?? [];

        $rows = [];
        $count = count($timestamps);
        for ($i = 0; $i < $count; $i++) {
            $close = $closes[$i] ?? null;
            if ($close === null || (float) $close <= 0.0) {
                continue;
            }
            // Konversi ke waktu exchange (Yahoo timestamp = UTC).
            $dt = gmdate('Y-m-d H:i:s', (int) $timestamps[$i] + $gmtoffset);
            $rows[] = new IntradayBar(
                symbol: strtoupper($symbol),
                datetime: $dt,
                interval: '1h',
                open: (float) ($opens[$i] ?? $close),
                high: (float) ($highs[$i] ?? $close),
                low: (float) ($lows[$i] ?? $close),
                close: (float) $close,
                volume: (int) ($volumes[$i] ?? 0),
            );
        }

        usort($rows, fn ($a, $b) => strcmp($a->datetime, $b->datetime));
        return $rows;
    }

    private function fetchChartBars(string $symbol, string $interval, int $fromTs, int $toTs, string $dateFormat): array
    {
        $fromTs = max($fromTs, strtotime('1970-01-01'));

        $this->rateLimit();

        $url = self::CHART_URL . urlencode(strtoupper(str_replace('.', '-', $symbol)))
            . '?period1=' . $fromTs . '&period2=' . $toTs . '&interval=' . $interval . '&includePrePost=false';

        $body = $this->curlGet($url);
        $data = json_decode($body, true);

        if (!isset($data['chart']['result'][0])) {
            $err = $data['chart']['error'] ?? null;
            throw new Exception("Yahoo Finance: error for {$symbol}" . ($err ? ' - ' . ($err['code'] ?? '') : ''));
        }

        $result = $data['chart']['result'][0];
        $timestamps = $result['timestamp'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? [];

        $opens = $quote['open'] ?? [];
        $highs = $quote['high'] ?? [];
        $lows = $quote['low'] ?? [];
        $closes = $quote['close'] ?? [];
        $volumes = $quote['volume'] ?? [];

        $rows = [];
        $count = count($timestamps);
        for ($i = 0; $i < $count; $i++) {
            $close = $closes[$i] ?? null;
            if ($close === null || (float) $close === 0.0) {
                continue;
            }
            $rows[] = new DailyBar(
                symbol: strtoupper($symbol),
                date: date($dateFormat, (int) $timestamps[$i]),
                open: (float) ($opens[$i] ?? $close),
                high: (float) ($highs[$i] ?? $close),
                low: (float) ($lows[$i] ?? $close),
                close: (float) $close,
                volume: (int) ($volumes[$i] ?? 0),
            );
        }

        usort($rows, fn ($a, $b) => strcmp($a->date, $b->date));

        if ($rows === []) {
            throw new Exception("Yahoo Finance: no valid bars for {$symbol}");
        }

        return $rows;
    }

    public function getStockList(): array
    {
        // Yahoo Finance screener API sering diblokir — gunakan daftar statis.
        // Untuk menambah saham, gunakan `php yii data/sync <symbol>` (auto-register).
        $stocks = [];
        foreach ($this->staticStockList() as $symbol => $info) {
            $stocks[] = [
                'symbol' => $symbol,
                'name' => $info['name'],
                'exchange' => $info['exchange'],
                'sector' => null,
                'industry' => null,
                'market_cap' => null,
            ];
        }
        return $stocks;
    }

    /**
     * Daftar saham US — fokus pada saham-saham besar di NASDAQ dan NYSE (~393 saham).
     * Untuk menambah: edit daftar ini atau gunakan `php yii data/sync <symbol>`.
     */
    private function staticStockList(): array
    {
        return [
            // === NASDAQ: Teknologi (Large-Cap) ===
            'AAPL'  => ['name' => 'Apple Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MSFT'  => ['name' => 'Microsoft Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'GOOGL' => ['name' => 'Alphabet Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'AMZN'  => ['name' => 'Amazon.com, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'NVDA'  => ['name' => 'NVIDIA Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'TSLA'  => ['name' => 'Tesla, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'META'  => ['name' => 'Meta Platforms, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'NFLX'  => ['name' => 'Netflix, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'INTC'  => ['name' => 'Intel Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ADBE'  => ['name' => 'Adobe Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CMCSA' => ['name' => 'Comcast Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PEP'   => ['name' => 'PepsiCo, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'AVGO'  => ['name' => 'Broadcom Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ORCL'  => ['name' => 'Oracle Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'CSCO'  => ['name' => 'Cisco Systems, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ABNB'  => ['name' => 'Airbnb, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'AMD'   => ['name' => 'Advanced Micro Devices, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'INTU'  => ['name' => 'Intuit Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'TXN'   => ['name' => 'Texas Instruments Incorporated', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'AMAT'  => ['name' => 'Applied Materials, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ADP'   => ['name' => 'Automatic Data Processing, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CRM'   => ['name' => 'Salesforce, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'QCOM'  => ['name' => 'Qualcomm Incorporated', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'NXPI'  => ['name' => 'NXP Semiconductors N.V.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'DOCU'  => ['name' => 'DocuSign, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ASML'  => ['name' => 'ASML Holding plc', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MRVL'  => ['name' => 'Marvell Technology, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PANW'  => ['name' => 'Palo Alto Networks, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CRWD'  => ['name' => 'CrowdStrike Holdings, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'FTNT'  => ['name' => 'Fortinet, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SNOW'  => ['name' => 'Snowflake Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PLTR'  => ['name' => 'Palantir Technologies Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'COIN'  => ['name' => 'Coinbase Global, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MDB'   => ['name' => 'MongoDB, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MTCH'  => ['name' => 'Match Group, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SPOT'  => ['name' => 'Spotify Technology S.A.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'EA'    => ['name' => 'Electronic Arts Incorporated', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'TTWO'  => ['name' => 'Take-Two Interactive Software, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CDNS'  => ['name' => 'Cadence Design Systems, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ROP'   => ['name' => 'Roper Technologies, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MCHP'  => ['name' => 'Microchip Technology Incorporated', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CDW'   => ['name' => 'CDW Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'COST'  => ['name' => 'Costco Wholesale Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'GOOG'  => ['name' => 'Alphabet Inc. Class C', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'TMUS'  => ['name' => 'T-Mobile US, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MU'    => ['name' => 'Micron Technology, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'NOK'   => ['name' => 'Nokia Oyj', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SHOP'  => ['name' => 'Shopify Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ZM'    => ['name' => 'Zoom Video Communications, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'OKTA'  => ['name' => 'Okta, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'NET'   => ['name' => 'Cloudflare, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'TEAM'  => ['name' => 'Atlassian Corporation Plc', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ROKU'  => ['name' => 'Roku, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'LCID'  => ['name' => 'Lucid Motors', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ENPH'  => ['name' => 'Enphase Energy, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PLUG'  => ['name' => 'Plug Power Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SBUX'  => ['name' => 'Starbucks Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'RIOT'  => ['name' => 'Riot Platforms, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MARA'  => ['name' => 'Marathon Digital Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PYPL'  => ['name' => 'PayPal Holdings, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === NASDAQ: Kesehatan ===
            'BIIB'  => ['name' => 'Biogen Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'REGN'  => ['name' => 'Regeneron Pharmaceuticals, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'VRTX'  => ['name' => 'Vertex Pharmaceuticals Incorporated', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'GILD'  => ['name' => 'Gilead Sciences, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'BMY'   => ['name' => 'Bristol-Myers Squibb Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'AMGN'  => ['name' => 'Amgen Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MRK'   => ['name' => 'Merck & Co., Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PFE'   => ['name' => 'Pfizer Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ABBV'  => ['name' => 'AbbVie Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ALGN'  => ['name' => 'Align Technology, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'DXCM'  => ['name' => 'DexCom, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === NASDAQ: Keuangan & Fintech ===
            'V'     => ['name' => 'Visa Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PAYX'  => ['name' => 'Paychex, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === NASDAQ: Real Estate ===
            'O'     => ['name' => 'Realty Income Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PLD'   => ['name' => 'Prologis, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === NYSE ===
            'TSM'   => ['name' => 'Taiwan Semiconductor Manufacturing Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'LLY'   => ['name' => 'Eli Lilly and Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'XOM'   => ['name' => 'Exxon Mobil Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'JNJ'   => ['name' => 'Johnson & Johnson', 'exchange' => Stock::EXCHANGE_NYSE],
            'WMT'   => ['name' => 'Walmart Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'JPM'   => ['name' => 'JPMorgan Chase and Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'BRK.B' => ['name' => 'Berkshire Hathaway Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'KO'    => ['name' => 'The Coca-Cola Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'IBM'   => ['name' => 'International Business Machines Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'NVO'   => ['name' => 'Novo Nordisk A/S', 'exchange' => Stock::EXCHANGE_NYSE],
            'MA'    => ['name' => 'Mastercard Incorporated', 'exchange' => Stock::EXCHANGE_NYSE],
            'DIS'   => ['name' => 'The Walt Disney Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'CAT'   => ['name' => 'Caterpillar Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'MCD'   => ['name' => 'McDonald\'s Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'BA'    => ['name' => 'Boeing Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'DE'    => ['name' => 'Deere & Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'MMM'   => ['name' => '3M Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'F'     => ['name' => 'Ford Motor Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'GM'    => ['name' => 'General Motors Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'BAC'   => ['name' => 'Bank of America Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'GS'    => ['name' => 'The Goldman Sachs Group, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'MS'    => ['name' => 'Morgan Stanley', 'exchange' => Stock::EXCHANGE_NYSE],
            'BLK'   => ['name' => 'BlackRock, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'TM'    => ['name' => 'Toyota Motor Corporation', 'exchange' => Stock::EXCHANGE_NYSE],

            // === NYSE: Consumer & Retail (tambahan) ===
            'HD'    => ['name' => 'Home Depot Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'NKE'   => ['name' => 'Nike Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'LOW'   => ['name' => "Lowe's Companies Inc.", 'exchange' => Stock::EXCHANGE_NYSE],
            'TJX'   => ['name' => 'TJX Companies Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'BKNG'  => ['name' => 'Booking Holdings Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PG'    => ['name' => 'Procter & Gamble Company', 'exchange' => Stock::EXCHANGE_NYSE],
            // === NASDAQ: Semikonduktor & Hardware (tambahan) ===
            'ADI'   => ['name' => 'Analog Devices, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'KLAC'  => ['name' => 'KLA Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'WDC'   => ['name' => 'Western Digital Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'LRCX'  => ['name' => 'Lam Research Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'STX'   => ['name' => 'Seagate Technology Holdings plc', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === NASDAQ: Solar / Clean Energy (tambahan) ===
            'SEDG'  => ['name' => 'SolarEdge Technologies, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'FSLR'  => ['name' => 'First Solar, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'RUN'   => ['name' => 'Sunrun Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CSIQ'  => ['name' => 'Canadian Solar Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === NASDAQ: E-commerce & Consumer (tambahan) ===
            'ETSY'  => ['name' => 'Etsy, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MELI'  => ['name' => 'MercadoLibre, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'JD'    => ['name' => 'JD.com, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PDD'   => ['name' => 'PDD Holdings Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'BIDU'  => ['name' => 'Baidu, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === NASDAQ: Gaming / Betting (tambahan) ===
            'DKNG'  => ['name' => 'DraftKings Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PENN'  => ['name' => 'PENN Entertainment, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CZR'   => ['name' => 'Caesars Entertainment, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === NYSE: Industrial, Defense & Tobacco (tambahan) ===
            'GE'    => ['name' => 'GE Aerospace', 'exchange' => Stock::EXCHANGE_NYSE],
            'RTX'   => ['name' => 'RTX Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'PM'    => ['name' => 'Philip Morris International Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'BABA'  => ['name' => 'Alibaba Group Holding Limited', 'exchange' => Stock::EXCHANGE_NYSE],
            'MGM'   => ['name' => 'MGM Resorts International', 'exchange' => Stock::EXCHANGE_NYSE],
            'FLUT'  => ['name' => 'Flutter Entertainment plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'LMT'   => ['name' => 'Lockheed Martin Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'NOC'   => ['name' => 'Northrop Grumman Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'GD'    => ['name' => 'General Dynamics Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'MO'    => ['name' => 'Altria Group, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],

            // === NYSE: SaaS & Industrial (tambahan) ===
            'NOW'   => ['name' => 'ServiceNow, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'HON'   => ['name' => 'Honeywell International Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === NYSE Arca: ETF Inovasi (sejenis SPCX) ===
            'SPCX'  => ['name' => 'SPAC and New Issue ETF', 'exchange' => Stock::EXCHANGE_NYSE],
            'ARKK'  => ['name' => 'ARK Innovation ETF', 'exchange' => Stock::EXCHANGE_NYSE],

            // === Tambahan Saham Pilihan (US & Global ADRs) ===
            'SKHY'  => ['name' => 'SK hynix Inc. ADR', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CVX'   => ['name' => 'Chevron Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'DELL'  => ['name' => 'Dell Technologies Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'UNH'   => ['name' => 'UnitedHealth Group Incorporated', 'exchange' => Stock::EXCHANGE_NYSE],
            'HSBC'  => ['name' => 'HSBC Holdings plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'ARM'   => ['name' => 'Arm Holdings plc', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SHEL'  => ['name' => 'Shell plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'GEV'   => ['name' => 'GE Vernova Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'ANET'  => ['name' => 'Arista Networks, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'RY'    => ['name' => 'Royal Bank of Canada', 'exchange' => Stock::EXCHANGE_NYSE],
            'NVS'   => ['name' => 'Novartis AG', 'exchange' => Stock::EXCHANGE_NYSE],
            'WFC'   => ['name' => 'Wells Fargo & Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'AZN'   => ['name' => 'AstraZeneca PLC', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SAP'   => ['name' => 'SAP SE', 'exchange' => Stock::EXCHANGE_NYSE],
            'SNDK'  => ['name' => 'SanDisk Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'LIN'   => ['name' => 'Linde plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'BHP'   => ['name' => 'BHP Group Limited', 'exchange' => Stock::EXCHANGE_NYSE],
            'C'     => ['name' => 'Citigroup Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'AXP'   => ['name' => 'American Express Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'SAN'   => ['name' => 'Banco Santander, S.A.', 'exchange' => Stock::EXCHANGE_NYSE],
            'TD'    => ['name' => 'The Toronto-Dominion Bank', 'exchange' => Stock::EXCHANGE_NYSE],
            'VZ'    => ['name' => 'Verizon Communications Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'TTE'   => ['name' => 'TotalEnergies SE', 'exchange' => Stock::EXCHANGE_NYSE],
            'ETN'   => ['name' => 'Eaton Corporation plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'SCCO'  => ['name' => 'Southern Copper Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'ABT'   => ['name' => 'Abbott Laboratories', 'exchange' => Stock::EXCHANGE_NYSE],
            'T'     => ['name' => 'AT&T Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'SCHW'  => ['name' => 'The Charles Schwab Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'UNP'   => ['name' => 'Union Pacific Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'NEE'   => ['name' => 'NextEra Energy, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'COP'   => ['name' => 'ConocoPhillips', 'exchange' => Stock::EXCHANGE_NYSE],
            'RIO'   => ['name' => 'Rio Tinto Group', 'exchange' => Stock::EXCHANGE_NYSE],
            'DHR'   => ['name' => 'Danaher Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'BBVA'  => ['name' => 'Banco Bilbao Vizcaya Argentaria, S.A.', 'exchange' => Stock::EXCHANGE_NYSE],
            'BUD'   => ['name' => 'Anheuser-Busch InBev SA/NV', 'exchange' => Stock::EXCHANGE_NYSE],
            'PBR'   => ['name' => 'Petróleo Brasileiro S.A. - Petrobras', 'exchange' => Stock::EXCHANGE_NYSE],
            'GLW'   => ['name' => 'Corning Incorporated', 'exchange' => Stock::EXCHANGE_NYSE],
            'ISRG'  => ['name' => 'Intuitive Surgical, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'UBER'  => ['name' => 'Uber Technologies, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'SONY'  => ['name' => 'Sony Group Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'UL'    => ['name' => 'Unilever PLC', 'exchange' => Stock::EXCHANGE_NYSE],
            'CB'    => ['name' => 'Chubb Limited', 'exchange' => Stock::EXCHANGE_NYSE],
            'PGR'   => ['name' => 'The Progressive Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'NEM'   => ['name' => 'Newmont Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'ASX'   => ['name' => 'ASE Technology Holding Co., Ltd.', 'exchange' => Stock::EXCHANGE_NYSE],
            'MPC'   => ['name' => 'Marathon Petroleum Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'VLO'   => ['name' => 'Valero Energy Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'COF'   => ['name' => 'Capital One Financial Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'ACN'   => ['name' => 'Accenture plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'SPGI'  => ['name' => 'S&P Global Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'BMO'   => ['name' => 'Bank of Montreal', 'exchange' => Stock::EXCHANGE_NYSE],
            'BTI'   => ['name' => 'British American Tobacco p.l.c.', 'exchange' => Stock::EXCHANGE_NYSE],
            'MDT'   => ['name' => 'Medtronic plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'CVS'   => ['name' => 'CVS Health Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'BNS'   => ['name' => 'The Bank of Nova Scotia', 'exchange' => Stock::EXCHANGE_NYSE],
            'PWR'   => ['name' => 'Quanta Services, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'PSX'   => ['name' => 'Phillips 66', 'exchange' => Stock::EXCHANGE_NYSE],
            'MCK'   => ['name' => 'McKesson Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'SYK'   => ['name' => 'Stryker Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'CEG'   => ['name' => 'Constellation Energy Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'FCX'   => ['name' => 'Freeport-McMoRan Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'ENB'   => ['name' => 'Enbridge Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'EQIX'  => ['name' => 'Equinix, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'LITE'  => ['name' => 'Lumentum Holdings Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'EQNR'  => ['name' => 'Equinor ASA', 'exchange' => Stock::EXCHANGE_NYSE],
            'HOOD'  => ['name' => 'Robinhood Markets, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'DDOG'  => ['name' => 'Datadog, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ING'   => ['name' => 'ING Groep N.V.', 'exchange' => Stock::EXCHANGE_NYSE],
            'CNQ'   => ['name' => 'Canadian Natural Resources Limited', 'exchange' => Stock::EXCHANGE_NYSE],
            'SO'    => ['name' => 'The Southern Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'BNY'   => ['name' => 'The Bank of New York Mellon Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'VRT'   => ['name' => 'Vertiv Holdings Co', 'exchange' => Stock::EXCHANGE_NYSE],
            'SNPS'  => ['name' => 'Synopsys, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'JCI'   => ['name' => 'Johnson Controls International plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'SNY'   => ['name' => 'Sanofi', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MAR'   => ['name' => 'Marriott International, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'AEM'   => ['name' => 'Agnico Eagle Mines Limited', 'exchange' => Stock::EXCHANGE_NYSE],
            'HCA'   => ['name' => 'HCA Healthcare, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'HPE'   => ['name' => 'Hewlett Packard Enterprise Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'GSK'   => ['name' => 'GSK plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'APP'   => ['name' => 'AppLovin Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === AI, Cloud, Enterprise Software & Data (tambahan) ===
            'MSTR'  => ['name' => 'MicroStrategy Incorporated', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SMCI'  => ['name' => 'Super Micro Computer, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ZS'    => ['name' => 'Zscaler, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PATH'  => ['name' => 'UiPath Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'AI'    => ['name' => 'C3.ai, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'SYM'   => ['name' => 'Symbotic Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'IOT'   => ['name' => 'Samsara Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'ESTC'  => ['name' => 'Elastic N.V.', 'exchange' => Stock::EXCHANGE_NYSE],
            'CFLT'  => ['name' => 'Confluent, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'GTLB'  => ['name' => 'GitLab Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'HUBS'  => ['name' => 'HubSpot, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'MNDY'  => ['name' => 'Monday.com Ltd.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'TWLO'  => ['name' => 'Twilio Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'BILL'  => ['name' => 'BILL Holdings, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'DUOL'  => ['name' => 'Duolingo, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'RDDT'  => ['name' => 'Reddit, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'CAVA'  => ['name' => 'CAVA Group, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'HIMS'  => ['name' => 'Hims & Hers Health, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'ALAB'  => ['name' => 'Astera Labs, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'TEM'   => ['name' => 'Tempus AI, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'KVYO'  => ['name' => 'Klaviyo, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'NTNX'  => ['name' => 'Nutanix, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === Semikonduktor & Hardware Lanjutan (tambahan) ===
            'MPWR'  => ['name' => 'Monolithic Power Systems, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ON'    => ['name' => 'ON Semiconductor Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'TER'   => ['name' => 'Teradyne, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ENTG'  => ['name' => 'Entegris, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SWKS'  => ['name' => 'Skyworks Solutions, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'QRVO'  => ['name' => 'Qorvo, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MKSI'  => ['name' => 'MKS Instruments, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'RMBS'  => ['name' => 'Rambus Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'POWI'  => ['name' => 'Power Integrations, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'WOLF'  => ['name' => 'Wolfspeed, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'GFS'   => ['name' => 'GLOBALFOUNDRIES Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === EV, Otomotif, Antariksa & Aviation (tambahan) ===
            'RIVN'  => ['name' => 'Rivian Automotive, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'NIO'   => ['name' => 'NIO Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'LI'    => ['name' => 'Li Auto Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'XPEV'  => ['name' => 'XPENG Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'RKLB'  => ['name' => 'Rocket Lab USA, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'LUNR'  => ['name' => 'Intuitive Machines, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ASTS'  => ['name' => 'AST SpaceMobile, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'JOBY'  => ['name' => 'Joby Aviation, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'ACHR'  => ['name' => 'Archer Aviation Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'HMC'   => ['name' => 'Honda Motor Co., Ltd.', 'exchange' => Stock::EXCHANGE_NYSE],

            // === Fintech, Pembayaran Digital & Infrastruktur Kripto (tambahan) ===
            'SOFI'  => ['name' => 'SoFi Technologies, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'AFRM'  => ['name' => 'Affirm Holdings, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'UPST'  => ['name' => 'Upstart Holdings, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SQ'    => ['name' => 'Block, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'CLSK'  => ['name' => 'CleanSpark, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'IREN'  => ['name' => 'Iris Energy Limited', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CIFR'  => ['name' => 'Cipher Mining Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'HUT'   => ['name' => 'Hut 8 Corp.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'WULF'  => ['name' => 'TeraWulf Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CORZ'  => ['name' => 'Core Scientific, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'IBKR'  => ['name' => 'Interactive Brokers Group, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'NU'    => ['name' => 'Nu Holdings Ltd.', 'exchange' => Stock::EXCHANGE_NYSE],
            'TOST'  => ['name' => 'Toast, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],

            // === Dividend Champions, High Yield REITs & Utilities (tambahan) ===
            'MAIN'  => ['name' => 'Main Street Capital Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'STAG'  => ['name' => 'STAG Industrial, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'VICI'  => ['name' => 'VICI Properties Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'AGNC'  => ['name' => 'AGNC Investment Corp.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'NLY'   => ['name' => 'Annaly Capital Management, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'DLR'   => ['name' => 'Digital Realty Trust, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'AMT'   => ['name' => 'American Tower Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'CCI'   => ['name' => 'Crown Castle Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'SPG'   => ['name' => 'Simon Property Group, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'PSA'   => ['name' => 'Public Storage', 'exchange' => Stock::EXCHANGE_NYSE],
            'EXR'   => ['name' => 'Extra Space Storage Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'WELL'  => ['name' => 'Welltower Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'VTR'   => ['name' => 'Ventas, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'DUK'   => ['name' => 'Duke Energy Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'AEP'   => ['name' => 'American Electric Power Company, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'EXC'   => ['name' => 'Exelon Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SRE'   => ['name' => 'Sempra', 'exchange' => Stock::EXCHANGE_NYSE],
            'ED'    => ['name' => 'Consolidated Edison, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],

            // === Energi, Tenaga Nuklir & Grid Listrik / AI Power (tambahan) ===
            'OXY'   => ['name' => 'Occidental Petroleum Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'SLB'   => ['name' => 'Schlumberger Limited', 'exchange' => Stock::EXCHANGE_NYSE],
            'HAL'   => ['name' => 'Halliburton Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'BKR'   => ['name' => 'Baker Hughes Company', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'EOG'   => ['name' => 'EOG Resources, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'DVN'   => ['name' => 'Devon Energy Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'KMI'   => ['name' => 'Kinder Morgan, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'WMB'   => ['name' => 'The Williams Companies, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'CCJ'   => ['name' => 'Cameco Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'NXE'   => ['name' => 'NexGen Energy Ltd.', 'exchange' => Stock::EXCHANGE_NYSE],
            'VST'   => ['name' => 'Vistra Corp.', 'exchange' => Stock::EXCHANGE_NYSE],
            'TLN'   => ['name' => 'Talen Energy Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'NRG'   => ['name' => 'NRG Energy, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'CTRA'  => ['name' => 'Coterra Energy Inc.', 'exchange' => Stock::EXCHANGE_NYSE],

            // === Kesehatan, Bioteknologi & MedTech (tambahan) ===
            'MRNA'  => ['name' => 'Moderna, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ILMN'  => ['name' => 'Illumina, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'IDXX'  => ['name' => 'IDEXX Laboratories, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'BDX'   => ['name' => 'Becton, Dickinson and Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'BSX'   => ['name' => 'Boston Scientific Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'EW'    => ['name' => 'Edwards Lifesciences Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'ZTS'   => ['name' => 'Zoetis Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'CI'    => ['name' => 'The Cigna Group', 'exchange' => Stock::EXCHANGE_NYSE],
            'ELV'   => ['name' => 'Elevance Health, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'HUM'   => ['name' => 'Humana Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'CNC'   => ['name' => 'Centene Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'VTRS'  => ['name' => 'Viatris Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CRSP'  => ['name' => 'CRISPR Therapeutics AG', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'INCY'  => ['name' => 'Incyte Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ALNY'  => ['name' => 'Alnylam Pharmaceuticals, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === Konsumer, Ritel, Makanan & Restoran (tambahan) ===
            'TGT'   => ['name' => 'Target Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'DLTR'  => ['name' => 'Dollar Tree, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'DG'    => ['name' => 'Dollar General Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'ROST'  => ['name' => 'Ross Stores, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'ORLY'  => ['name' => 'O\'Reilly Automotive, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'AZO'   => ['name' => 'AutoZone, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'MNST'  => ['name' => 'Monster Beverage Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CELH'  => ['name' => 'Celsius Holdings, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'KDP'   => ['name' => 'Keurig Dr Pepper Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'MDLZ'  => ['name' => 'Mondelez International, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'GIS'   => ['name' => 'General Mills, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'HSY'   => ['name' => 'The Hershey Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'CL'    => ['name' => 'Colgate-Palmolive Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'KMB'   => ['name' => 'Kimberly-Clark Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'LULU'  => ['name' => 'Lululemon Athletica Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'DECK'  => ['name' => 'Deckers Outdoor Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'ONON'  => ['name' => 'On Holding AG', 'exchange' => Stock::EXCHANGE_NYSE],
            'CROX'  => ['name' => 'Crocs, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CMG'   => ['name' => 'Chipotle Mexican Grill, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'YUM'   => ['name' => 'Yum! Brands, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'DPZ'   => ['name' => 'Domino\'s Pizza, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'DASH'  => ['name' => 'DoorDash, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === Transportasi, Maskapai, Pariwisata & Logistik (tambahan) ===
            'DAL'   => ['name' => 'Delta Air Lines, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'UAL'   => ['name' => 'United Airlines Holdings, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'LUV'   => ['name' => 'Southwest Airlines Co.', 'exchange' => Stock::EXCHANGE_NYSE],
            'AAL'   => ['name' => 'American Airlines Group Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'CCL'   => ['name' => 'Carnival Corporation & plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'RCL'   => ['name' => 'Royal Caribbean Cruises Ltd.', 'exchange' => Stock::EXCHANGE_NYSE],
            'NCLH'  => ['name' => 'Norwegian Cruise Line Holdings Ltd.', 'exchange' => Stock::EXCHANGE_NYSE],
            'HLT'   => ['name' => 'Hilton Worldwide Holdings Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'EXPE'  => ['name' => 'Expedia Group, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'FDX'   => ['name' => 'FedEx Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'UPS'   => ['name' => 'United Parcel Service, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'CSX'   => ['name' => 'CSX Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'NSC'   => ['name' => 'Norfolk Southern Corporation', 'exchange' => Stock::EXCHANGE_NYSE],

            // === Manufaktur, Industri, Bahan Baku & Pertahanan (tambahan) ===
            'WM'    => ['name' => 'Waste Management, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'RSG'   => ['name' => 'Republic Services, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'CTAS'  => ['name' => 'Cintas Corporation', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PH'    => ['name' => 'Parker-Hannifin Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'EMR'   => ['name' => 'Emerson Electric Co.', 'exchange' => Stock::EXCHANGE_NYSE],
            'ITW'   => ['name' => 'Illinois Tool Works Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'TT'    => ['name' => 'Trane Technologies plc', 'exchange' => Stock::EXCHANGE_NYSE],
            'CARR'  => ['name' => 'Carrier Global Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'OTIS'  => ['name' => 'Otis Worldwide Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'TDG'   => ['name' => 'TransDigm Group Incorporated', 'exchange' => Stock::EXCHANGE_NYSE],
            'LHX'   => ['name' => 'L3Harris Technologies, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'AXON'  => ['name' => 'Axon Enterprise, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'VMC'   => ['name' => 'Vulcan Materials Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'MLM'   => ['name' => 'Martin Marietta Materials, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'ECL'   => ['name' => 'Ecolab Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'APD'   => ['name' => 'Air Products and Chemicals, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'SHW'   => ['name' => 'The Sherwin-Williams Company', 'exchange' => Stock::EXCHANGE_NYSE],
            'NUE'   => ['name' => 'Nucor Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'CLF'   => ['name' => 'Cleveland-Cliffs Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'AA'    => ['name' => 'Alcoa Corporation', 'exchange' => Stock::EXCHANGE_NYSE],

            // === Indeks & ETF Populer Traded di US (tambahan) ===
            'SPY'   => ['name' => 'SPDR S&P 500 ETF Trust', 'exchange' => Stock::EXCHANGE_NYSE],
            'QQQ'   => ['name' => 'Invesco QQQ Trust', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'IWM'   => ['name' => 'iShares Russell 2000 ETF', 'exchange' => Stock::EXCHANGE_NYSE],
            'DIA'   => ['name' => 'SPDR Dow Jones Industrial Average ETF Trust', 'exchange' => Stock::EXCHANGE_NYSE],
            'SMH'   => ['name' => 'VanEck Semiconductor ETF', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SOXX'  => ['name' => 'iShares Semiconductor ETF', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'XLF'   => ['name' => 'Financial Select Sector SPDR Fund', 'exchange' => Stock::EXCHANGE_NYSE],
            'XLE'   => ['name' => 'Energy Select Sector SPDR Fund', 'exchange' => Stock::EXCHANGE_NYSE],
            'XLK'   => ['name' => 'Technology Select Sector SPDR Fund', 'exchange' => Stock::EXCHANGE_NYSE],
            'TLT'   => ['name' => 'iShares 20+ Year Treasury Bond ETF', 'exchange' => Stock::EXCHANGE_NASDAQ],

            // === Media Hiburan, Superapp & Global Growth / ADRs (tambahan) ===
            'RBLX'  => ['name' => 'Roblox Corporation', 'exchange' => Stock::EXCHANGE_NYSE],
            'WBD'   => ['name' => 'Warner Bros. Discovery, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'PARA'  => ['name' => 'Paramount Global', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'LYV'   => ['name' => 'Live Nation Entertainment, Inc.', 'exchange' => Stock::EXCHANGE_NYSE],
            'NTES'  => ['name' => 'NetEase, Inc.', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'SE'    => ['name' => 'Sea Limited', 'exchange' => Stock::EXCHANGE_NYSE],
            'GRAB'  => ['name' => 'Grab Holdings Limited', 'exchange' => Stock::EXCHANGE_NASDAQ],
            'TLK'   => ['name' => 'PT Telekomunikasi Indonesia Tbk ADR', 'exchange' => Stock::EXCHANGE_NYSE],
        ];
    }

    /**
     * cURL GET request.
     */
    private function curlGet(string $url): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: {$error}");
        }

        if ($statusCode >= 400) {
            throw new Exception("Yahoo Finance HTTP {$statusCode} for {$url}");
        }

        return (string) $response;
    }

    private function rateLimit(): void
    {
        $now = microtime(true);
        $this->timestamps = array_values(array_filter($this->timestamps, fn ($t) => ($now - $t) < 60));
        if (count($this->timestamps) >= $this->rateLimitPerMinute) {
            $sleepUntil = $this->timestamps[0] + 61;
            if ($sleepUntil > $now) {
                usleep((int) (($sleepUntil - $now) * 1_000_000));
            }
        }
        $this->timestamps[] = microtime(true);
    }
}
