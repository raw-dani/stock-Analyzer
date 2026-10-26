<?php

declare(strict_types=1);

namespace app\services;

use app\models\Stock;
use app\models\StockDividend;
use app\models\StockDividendSummary;
use Yii;

/**
 * Service untuk Dividend Hunter:
 * Sinkronisasi data dividen dari Yahoo Finance, screener & ranking, kalender ex-date,
 * dan kalkulator passive income / DRIP (Dividend Reinvestment Plan).
 */
final class DividendService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const CHART_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';

    /**
     * Daftar simbol Dividend Aristocrats / Champions populer US.
     */
    private const ARISTOCRATS = [
        'JNJ', 'PG', 'KO', 'PEP', 'ABBV', 'MMM', 'CL', 'MCD', 'WMT', 'T', 'VZ',
        'CVX', 'XOM', 'IBM', 'CAT', 'O', 'LOW', 'EMR', 'GPC', 'ADM', 'ED', 'SWK'
    ];

    /**
     * Sinkronisasi data dividen untuk 1 saham tertentu via Yahoo Finance chart API.
     */
    public function syncStockDividends(string $symbol, ?int $stockId = null): array
    {
        $symbol = strtoupper(trim($symbol));
        if ($stockId === null) {
            $stock = Stock::findOne(['symbol' => $symbol]);
            if (!$stock) {
                return ['success' => false, 'message' => "Saham {$symbol} tidak ditemukan di database."];
            }
            $stockId = $stock->id;
            $companyName = $stock->name;
            $sector = $stock->sector;
        } else {
            $stock = Stock::findOne($stockId);
            $companyName = $stock ? $stock->name : $symbol;
            $sector = $stock ? $stock->sector : null;
        }

        // Ambil data 3 tahun ke belakang
        $fromTs = strtotime('-3 years');
        $toTs = time();
        $url = self::CHART_URL . urlencode(str_replace('.', '-', $symbol))
            . '?period1=' . $fromTs . '&period2=' . $toTs . '&interval=1d&events=div%7Csplit';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return ['success' => false, 'message' => "Gagal mengambil data Yahoo Finance untuk {$symbol} (HTTP {$httpCode})."];
        }

        $data = json_decode($response, true);
        $result = $data['chart']['result'][0] ?? null;
        if (!$result) {
            return ['success' => false, 'message' => "Respon data Yahoo Finance kosong untuk {$symbol}."];
        }

        $meta = $result['meta'] ?? [];
        $currentPrice = (float) ($meta['regularMarketPrice'] ?? 0);
        if ($currentPrice <= 0 && !empty($meta['chartPreviousClose'])) {
            $currentPrice = (float) $meta['chartPreviousClose'];
        }

        $divEvents = $result['events']['dividends'] ?? [];
        $insertedCount = 0;
        $divList = [];

        foreach ($divEvents as $ts => $event) {
            $exDate = date('Y-m-d', (int) $ts);
            $amount = (float) ($event['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $divList[$exDate] = $amount;

            // Upsert ke StockDividend
            $record = StockDividend::findOne(['symbol' => $symbol, 'ex_date' => $exDate]);
            if (!$record) {
                $record = new StockDividend();
                $record->stock_id = $stockId;
                $record->symbol = $symbol;
                $record->ex_date = $exDate;
                $record->amount = $amount;
                if ($record->save()) {
                    $insertedCount++;
                }
            } else {
                $record->amount = $amount;
                $record->save();
            }
        }

        ksort($divList);

        // Hitung metrik dividen
        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));
        $ttmDividend = 0.0;
        $payoutCount1Y = 0;
        $lastExDate = null;
        $lastAmount = 0.0;

        foreach ($divList as $dDate => $dAmount) {
            $lastExDate = $dDate;
            $lastAmount = $dAmount;
            if ($dDate >= $oneYearAgo) {
                $ttmDividend += $dAmount;
                $payoutCount1Y++;
            }
        }

        // Tentukan frekuensi
        if ($payoutCount1Y >= 10) {
            $frequency = StockDividendSummary::FREQ_MONTHLY;
            $payoutsPerYear = 12;
        } elseif ($payoutCount1Y >= 3) {
            $frequency = StockDividendSummary::FREQ_QUARTERLY;
            $payoutsPerYear = 4;
        } elseif ($payoutCount1Y === 2) {
            $frequency = StockDividendSummary::FREQ_SEMI_ANNUAL;
            $payoutsPerYear = 2;
        } elseif ($payoutCount1Y === 1) {
            $frequency = StockDividendSummary::FREQ_ANNUAL;
            $payoutsPerYear = 1;
        } else {
            $frequency = count($divList) > 0 ? StockDividendSummary::FREQ_QUARTERLY : StockDividendSummary::FREQ_NONE;
            $payoutsPerYear = count($divList) > 0 ? 4 : 0;
        }

        // Hitung Dividend Yield %
        $yield = ($currentPrice > 0 && $ttmDividend > 0) ? ($ttmDividend / $currentPrice) * 100 : 0.0;

        // Estimasi tanggal Ex-Date berikutnya
        $estimatedNextExDate = null;
        if ($lastExDate) {
            $intervalDays = match ($frequency) {
                StockDividendSummary::FREQ_MONTHLY => 30,
                StockDividendSummary::FREQ_SEMI_ANNUAL => 182,
                StockDividendSummary::FREQ_ANNUAL => 365,
                default => 91, // quarterly
            };
            $nextTs = strtotime($lastExDate . ' +' . $intervalDays . ' days');
            // Jika tanggal sudah lewat, majukan terus per siklus
            while ($nextTs < time() - (7 * 86400)) {
                $nextTs += ($intervalDays * 86400);
            }
            $estimatedNextExDate = date('Y-m-d', $nextTs);
        }

        // Hitung Safety Score
        $consecutiveYears = $this->calculateConsecutiveYears($divList);
        $safetyScore = $this->determineSafetyScore($symbol, $yield, $consecutiveYears);

        // Upsert ke StockDividendSummary
        $summary = StockDividendSummary::findOne(['symbol' => $symbol]);
        if (!$summary) {
            $summary = new StockDividendSummary();
            $summary->stock_id = $stockId;
            $summary->symbol = $symbol;
        }
        $summary->company_name = $companyName;
        $summary->sector = $sector;
        $summary->current_price = round($currentPrice, 2);
        $summary->ttm_dividend = round($ttmDividend, 4);
        $summary->dividend_yield = round($yield, 2);
        $summary->payout_frequency = $frequency;
        $summary->payouts_per_year = $payoutsPerYear;
        $summary->last_ex_date = $lastExDate;
        $summary->last_amount = $lastAmount > 0 ? round($lastAmount, 4) : null;
        $summary->estimated_next_ex_date = $estimatedNextExDate;
        $summary->consecutive_years = $consecutiveYears;
        $summary->safety_score = $safetyScore;
        $summary->save();

        return [
            'success' => true,
            'symbol' => $symbol,
            'current_price' => $currentPrice,
            'ttm_dividend' => $ttmDividend,
            'dividend_yield' => $yield,
            'frequency' => $frequency,
            'last_ex_date' => $lastExDate,
            'estimated_next_ex_date' => $estimatedNextExDate,
            'safety_score' => $safetyScore,
            'inserted_payouts' => $insertedCount,
        ];
    }

    /**
     * Hitung berapa tahun berturut-turut saham membagikan dividen dari riwayat yang ada.
     */
    private function calculateConsecutiveYears(array $divList): int
    {
        if (empty($divList)) {
            return 0;
        }
        $years = [];
        foreach (array_keys($divList) as $dDate) {
            $y = (int) substr($dDate, 0, 4);
            $years[$y] = true;
        }
        return count($years);
    }

    /**
     * Tentukan klasifikasi keamanan dividen.
     */
    private function determineSafetyScore(string $symbol, float $yield, int $consecutiveYears): string
    {
        if (in_array($symbol, self::ARISTOCRATS, true)) {
            return StockDividendSummary::SAFETY_ARISTOCRAT;
        }
        if ($yield > 11.5) {
            return StockDividendSummary::SAFETY_WARNING;
        }
        if ($consecutiveYears >= 3 && $yield >= 1.5 && $yield <= 8.5) {
            return StockDividendSummary::SAFETY_SAFE;
        }
        return StockDividendSummary::SAFETY_MODERATE;
    }

    /**
     * Sinkronisasi dividen untuk seluruh saham aktif (dengan micro-pause CPU throttle).
     */
    public function syncAllActiveStocks(?int $limit = null, ?callable $onProgress = null): array
    {
        $query = Stock::find()->where(['active' => true])->orderBy(['symbol' => SORT_ASC]);
        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }
        $stocks = $query->all();

        $total = count($stocks);
        $synced = 0;
        $failed = 0;
        $dividendPayers = 0;

        foreach ($stocks as $idx => $stock) {
            $result = $this->syncStockDividends($stock->symbol, (int) $stock->id);
            if ($result['success'] ?? false) {
                $synced++;
                if (($result['dividend_yield'] ?? 0) > 0) {
                    $dividendPayers++;
                }
            } else {
                $failed++;
            }

            if ($onProgress !== null) {
                $onProgress($idx + 1, $total, $stock->symbol, $result);
            }

            // Micro-pause 30ms agar tidak membebani server dan tidak terkena rate-limit Yahoo
            usleep(30000);
        }

        return [
            'total' => $total,
            'synced' => $synced,
            'failed' => $failed,
            'dividend_payers' => $dividendPayers,
        ];
    }

    /**
     * Ambil data untuk Dividend Screener & Ranking dengan filter lengkap.
     */
    public function getScreenerData(array $filters = []): array
    {
        $query = StockDividendSummary::find();

        // Filter: Hanya yang membagikan dividen (yield > 0) kecuali diminta semua
        $includeZero = !empty($filters['include_zero']);
        if (!$includeZero) {
            $query->andWhere(['>', 'dividend_yield', 0]);
        }

        // Filter: Pencarian Symbol / Nama
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->andWhere(['or',
                ['like', 'symbol', $search],
                ['like', 'company_name', $search],
            ]);
        }

        // Filter: Yield Minimal
        if (isset($filters['min_yield']) && is_numeric($filters['min_yield']) && (float) $filters['min_yield'] > 0) {
            $query->andWhere(['>=', 'dividend_yield', (float) $filters['min_yield']]);
        }

        // Filter: Yield Maksimal (untuk menghindari yield trap)
        if (isset($filters['max_yield']) && is_numeric($filters['max_yield']) && (float) $filters['max_yield'] > 0) {
            $query->andWhere(['<=', 'dividend_yield', (float) $filters['max_yield']]);
        }

        // Filter: Frekuensi
        if (!empty($filters['frequency']) && $filters['frequency'] !== 'all') {
            $query->andWhere(['payout_frequency' => $filters['frequency']]);
        }

        // Filter: Safety Score
        if (!empty($filters['safety']) && $filters['safety'] !== 'all') {
            $query->andWhere(['safety_score' => $filters['safety']]);
        }

        // Filter: Sektor
        if (!empty($filters['sector']) && $filters['sector'] !== 'all') {
            $query->andWhere(['sector' => $filters['sector']]);
        }

        // Sorting
        $sort = $filters['sort'] ?? 'yield_desc';
        match ($sort) {
            'yield_asc' => $query->orderBy(['dividend_yield' => SORT_ASC]),
            'price_desc' => $query->orderBy(['current_price' => SORT_DESC]),
            'price_asc' => $query->orderBy(['current_price' => SORT_ASC]),
            'payout_desc' => $query->orderBy(['ttm_dividend' => SORT_DESC]),
            'symbol_asc' => $query->orderBy(['symbol' => SORT_ASC]),
            'next_date_asc' => $query->orderBy(['estimated_next_ex_date' => SORT_ASC]),
            default => $query->orderBy(['dividend_yield' => SORT_DESC, 'ttm_dividend' => SORT_DESC]),
        };

        return $query->all();
    }

    /**
     * Ambil data untuk Kalender Ex-Dividend (Upcoming & Recent Ex-Dates).
     */
    public function getCalendarData(int $limit = 50): array
    {
        $today = date('Y-m-d');
        
        // Ambil saham yang memiliki estimasi Ex-Date mendatang atau baru lewat 14 hari
        return StockDividendSummary::find()
            ->where(['>', 'dividend_yield', 0])
            ->andWhere(['not', ['estimated_next_ex_date' => null]])
            ->andWhere(['>=', 'estimated_next_ex_date', date('Y-m-d', strtotime('-14 days'))])
            ->orderBy(['estimated_next_ex_date' => SORT_ASC])
            ->limit($limit)
            ->all();
    }

    /**
     * Hitung simulasi Passive Income dan proyeksi DRIP (Dividend Reinvestment Plan).
     */
    public function calculatePassiveIncome(float $capital, ?string $symbol = null, ?float $customYield = null, int $years = 10): array
    {
        $capital = max(100.0, $capital);
        $stockSummary = null;
        $yield = $customYield ?? 4.5;
        $stockPrice = 100.0;
        $frequency = StockDividendSummary::FREQ_QUARTERLY;

        if (!empty($symbol)) {
            $stockSummary = StockDividendSummary::findOne(['symbol' => strtoupper(trim($symbol))]);
            if ($stockSummary && $stockSummary->dividend_yield > 0) {
                $yield = (float) $stockSummary->dividend_yield;
                $stockPrice = (float) $stockSummary->current_price;
                $frequency = $stockSummary->payout_frequency;
            }
        }

        // Estimasi Dividen Awal
        $annualDividend = $capital * ($yield / 100.0);
        $monthlyDividend = $annualDividend / 12.0;
        $quarterlyDividend = $annualDividend / 4.0;
        $sharesCount = $stockPrice > 0 ? floor($capital / $stockPrice) : 0;

        // Proyeksi Tahun ke Tahun (Dengan DRIP vs Tanpa DRIP)
        // Asumsi apresiasi modal moderat 4% per tahun + yield dividen
        $capitalAppreciationRate = 0.04;
        $timeline = [];
        $dripBalance = $capital;
        $nonDripTotalIncome = 0.0;
        $dripShares = $sharesCount;
        $currentSharePrice = $stockPrice;

        for ($y = 1; $y <= $years; $y++) {
            // Tanpa DRIP: dividen diambil tunai
            $annualIncomeNoDrip = $capital * ($yield / 100.0);
            $nonDripTotalIncome += $annualIncomeNoDrip;
            $nonDripPortfolioValue = $capital * pow(1 + $capitalAppreciationRate, $y);

            // Dengan DRIP: dividen dibelikan saham kembali
            $currentSharePrice *= (1 + $capitalAppreciationRate);
            $dripAnnualDiv = $dripBalance * ($yield / 100.0);
            $dripBalance += $dripAnnualDiv;
            $dripBalance *= (1 + $capitalAppreciationRate);

            $timeline[] = [
                'year' => $y,
                'no_drip_annual_income' => round($annualIncomeNoDrip, 2),
                'no_drip_cumulative_income' => round($nonDripTotalIncome, 2),
                'no_drip_portfolio_value' => round($nonDripPortfolioValue, 2),
                'drip_portfolio_value' => round($dripBalance, 2),
                'drip_annual_income' => round($dripAnnualDiv, 2),
                'drip_bonus_gain' => round($dripBalance - ($nonDripPortfolioValue + $nonDripTotalIncome), 2),
            ];
        }

        return [
            'capital' => $capital,
            'symbol' => $symbol,
            'stock_summary' => $stockSummary,
            'yield' => round($yield, 2),
            'stock_price' => round($stockPrice, 2),
            'shares_count' => $sharesCount,
            'annual_dividend' => round($annualDividend, 2),
            'monthly_dividend' => round($monthlyDividend, 2),
            'quarterly_dividend' => round($quarterlyDividend, 2),
            'frequency' => $frequency,
            'timeline' => $timeline,
            'final_drip_value' => round($dripBalance, 2),
            'final_no_drip_value' => round($capital * pow(1 + $capitalAppreciationRate, $years) + $nonDripTotalIncome, 2),
        ];
    }
}
