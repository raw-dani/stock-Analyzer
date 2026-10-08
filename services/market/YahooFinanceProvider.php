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
        // Default: 30 hari terakhir (hemat kuota; cukup untuk lookback 150 candle 4H).
        $toTs = $toDatetime ? strtotime($toDatetime) : time();
        $fromTs = $fromDatetime ? strtotime($fromDatetime) : strtotime('-30 days', $toTs);

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
     * Daftar saham US — fokus pada saham-saham besar di NASDAQ dan NYSE (~136 saham).
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
