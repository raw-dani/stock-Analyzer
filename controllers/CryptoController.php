<?php

declare(strict_types=1);

namespace app\controllers;

use app\assets\ChartAsset;
use app\models\CryptoAnalysis;
use app\models\CryptoAsset;
use app\models\CryptoPrice;
use app\services\crypto\CryptoAnalyzerService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller khusus Crypto Market & Volume Analyzer (terpisah dari modul saham).
 */
class CryptoController extends Controller
{
    private CryptoAnalyzerService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new CryptoAnalyzerService();
        $this->service->init();
    }

    /**
     * Crypto Scanner & Hub
     */
    public function actionIndex(): string
    {
        $timeframe = Yii::$app->request->get('timeframe', CryptoPrice::TIMEFRAME_1H);
        if (!array_key_exists($timeframe, CryptoPrice::SUPPORTED_TIMEFRAMES)) {
            $timeframe = CryptoPrice::TIMEFRAME_1H;
        }

        [$currency, $usdtIdrRate] = $this->resolveCurrency();

        $signalFilter = Yii::$app->request->get('signal', '');
        $search = trim((string)Yii::$app->request->get('search', ''));
        $sort = Yii::$app->request->get('sort', 'score_desc');

        // Pastikan koin default sudah ada
        $totalAssets = CryptoAsset::find()->count();
        if ($totalAssets === 0) {
            $this->service->refreshCoinList();
        }

        // Query asset & analisis untuk timeframe aktif
        $assets = CryptoAsset::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC])
            ->all();

        $rows = [];
        $strongBuyCount = 0;
        $heavyAccumCount = 0;
        $totalBuyRatio = 0.0;
        $validAnalysisCount = 0;
        $topGainer = null;
        $topLoser = null;

        foreach ($assets as $asset) {
            /** @var CryptoAnalysis|null $analysis */
            $analysis = CryptoAnalysis::findOne([
                'crypto_id' => $asset->id,
                'timeframe' => $timeframe,
            ]);

            // Jika belum ada analisis untuk timeframe ini, coba analisis cepat
            if ($analysis === null) {
                $hasBars = CryptoPrice::find()->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])->exists();
                if (!$hasBars) {
                    $this->service->syncCoinBars($asset, $timeframe, 60);
                }
                $analysis = $this->service->analyze($asset, $timeframe);
            }

            $currentPrice = $analysis ? (float)$analysis->current_price : 0.0;
            $change24h = $analysis ? (float)($analysis->price_change_24h ?? 0.0) : 0.0;
            $buyRatio = $analysis ? (float)$analysis->buy_ratio : 50.0;
            $rvol = $analysis ? (float)($analysis->rvol ?? 1.0) : 1.0;
            $rsi = $analysis && $analysis->rsi !== null ? (float)$analysis->rsi : null;
            $ma20 = $analysis ? (float)($analysis->ma20 ?? 0.0) : null;
            $ma50 = $analysis ? (float)($analysis->ma50 ?? 0.0) : null;
            $score = $analysis ? (int)$analysis->score : 50;
            $signal = $analysis ? $analysis->signal : CryptoAnalysis::SIGNAL_NEUTRAL;

            if ($analysis !== null) {
                $validAnalysisCount++;
                $totalBuyRatio += $buyRatio;
                if ($signal === CryptoAnalysis::SIGNAL_STRONG_BUY) {
                    $strongBuyCount++;
                }
                if ($buyRatio >= 65.0) {
                    $heavyAccumCount++;
                }
                if ($topGainer === null || $change24h > $topGainer['change']) {
                    $topGainer = ['symbol' => $asset->symbol, 'name' => $asset->name, 'change' => $change24h];
                }
                if ($topLoser === null || $change24h < $topLoser['change']) {
                    $topLoser = ['symbol' => $asset->symbol, 'name' => $asset->name, 'change' => $change24h];
                }
            }

            // Filter pencarian
            if ($search !== '') {
                $needle = strtoupper($search);
                if (!str_contains(strtoupper($asset->symbol), $needle) && !str_contains(strtoupper($asset->name), $needle)) {
                    continue;
                }
            }

            // Filter sinyal
            if ($signalFilter !== '' && $signal !== $signalFilter) {
                continue;
            }

            $rows[] = [
                'asset' => $asset,
                'analysis' => $analysis,
                'currentPrice' => $currentPrice,
                'change24h' => $change24h,
                'buyRatio' => $buyRatio,
                'sellRatio' => max(0.0, round(100.0 - $buyRatio, 2)),
                'rvol' => $rvol,
                'rsi' => $rsi,
                'ma20' => $ma20,
                'ma50' => $ma50,
                'score' => $score,
                'signal' => $signal,
            ];
        }

        // Sorting
        usort($rows, function ($a, $b) use ($sort) {
            return match ($sort) {
                'score_desc' => $b['score'] <=> $a['score'],
                'score_asc' => $a['score'] <=> $b['score'],
                'buy_ratio_desc' => $b['buyRatio'] <=> $a['buyRatio'],
                'rvol_desc' => $b['rvol'] <=> $a['rvol'],
                'change_desc' => $b['change24h'] <=> $a['change24h'],
                'change_asc' => $a['change24h'] <=> $b['change24h'],
                'name_asc' => strcmp($a['asset']->name, $b['asset']->name),
                default => $b['score'] <=> $a['score'],
            };
        });

        $avgBuyRatio = $validAnalysisCount > 0 ? round($totalBuyRatio / $validAnalysisCount, 1) : 50.0;

        return $this->render('index', [
            'timeframe' => $timeframe,
            'signalFilter' => $signalFilter,
            'search' => $search,
            'sort' => $sort,
            'rows' => $rows,
            'stats' => [
                'total' => count($assets),
                'filtered' => count($rows),
                'strongBuy' => $strongBuyCount,
                'heavyAccum' => $heavyAccumCount,
                'avgBuyRatio' => $avgBuyRatio,
                'topGainer' => $topGainer,
                'topLoser' => $topLoser,
            ],
            'currency' => $currency,
            'usdtIdrRate' => $usdtIdrRate,
        ]);
    }

    /**
     * Halaman Detail & Interactive Chart ECharts untuk satu koin
     */
    public function actionView(string $symbol, string $timeframe = CryptoPrice::TIMEFRAME_1H): string
    {
        ChartAsset::register($this->view);

        $symbol = strtoupper($symbol);
        $asset = CryptoAsset::findOne(['symbol' => $symbol]);
        if ($asset === null) {
            throw new NotFoundHttpException("Koin crypto {$symbol} tidak ditemukan.");
        }

        if (!array_key_exists($timeframe, CryptoPrice::SUPPORTED_TIMEFRAMES)) {
            $timeframe = CryptoPrice::TIMEFRAME_1H;
        }

        // Cek bar, jika kosong otomatis sync
        $barsCount = CryptoPrice::find()->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])->count();
        if ($barsCount < 10) {
            $this->service->syncCoinBars($asset, $timeframe, 100);
            $this->service->analyze($asset, $timeframe);
        }

        $analysis = CryptoAnalysis::findOne([
            'crypto_id' => $asset->id,
            'timeframe' => $timeframe,
        ]);

        $chartData = $this->buildChartPayload($asset, $timeframe);

        [$currency, $usdtIdrRate] = $this->resolveCurrency();

        return $this->render('view', [
            'asset' => $asset,
            'timeframe' => $timeframe,
            'analysis' => $analysis,
            'chartData' => $chartData,
            'currency' => $currency,
            'usdtIdrRate' => $usdtIdrRate,
        ]);
    }

    /**
     * JSON Endpoint untuk AJAX chart saat switcher timeframe diklik tanpa reload
     */
    public function actionChartData(string $symbol, string $timeframe = CryptoPrice::TIMEFRAME_1H): Response
    {
        $symbol = strtoupper($symbol);
        $asset = CryptoAsset::findOne(['symbol' => $symbol]);
        if ($asset === null) {
            return $this->asJson(['success' => false, 'message' => 'Asset not found']);
        }

        if (!array_key_exists($timeframe, CryptoPrice::SUPPORTED_TIMEFRAMES)) {
            $timeframe = CryptoPrice::TIMEFRAME_1H;
        }

        // Pastikan ada bar
        $barsCount = CryptoPrice::find()->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])->count();
        if ($barsCount < 10) {
            $this->service->syncCoinBars($asset, $timeframe, 100);
            $this->service->analyze($asset, $timeframe);
        }

        $payload = $this->buildChartPayload($asset, $timeframe);
        $analysis = CryptoAnalysis::findOne(['crypto_id' => $asset->id, 'timeframe' => $timeframe]);

        return $this->asJson([
            'success' => true,
            'timeframe' => $timeframe,
            'analysis' => [
                'current_price' => $analysis ? (float)$analysis->current_price : 0,
                'price_change_24h' => $analysis ? (float)($analysis->price_change_24h ?? 0) : 0,
                'buy_ratio' => $analysis ? (float)$analysis->buy_ratio : 50,
                'rvol' => $analysis ? (float)($analysis->rvol ?? 1) : 1,
                'rsi' => $analysis ? (float)($analysis->rsi ?? 50) : 50,
                'score' => $analysis ? (int)$analysis->score : 50,
                'signal' => $analysis ? $analysis->signal : 'NEUTRAL',
            ],
            'chart' => $payload,
        ]);
    }

    /**
     * AJAX Live Sync: Sinkronisasi instan satu koin ke Binance
     */
    public function actionLiveSync(string $symbol, string $timeframe = CryptoPrice::TIMEFRAME_1H): Response
    {
        $symbol = strtoupper($symbol);
        $asset = CryptoAsset::findOne(['symbol' => $symbol]);
        if ($asset === null) {
            return $this->asJson(['success' => false, 'message' => 'Asset not found']);
        }

        $saved = $this->service->syncCoinBars($asset, $timeframe, 100);
        $analysis = $this->service->analyze($asset, $timeframe);

        return $this->asJson([
            'success' => true,
            'saved' => $saved,
            'analysis' => $analysis ? [
                'current_price' => (float)$analysis->current_price,
                'price_change_24h' => (float)($analysis->price_change_24h ?? 0),
                'buy_ratio' => (float)$analysis->buy_ratio,
                'rvol' => (float)($analysis->rvol ?? 1),
                'rsi' => (float)($analysis->rsi ?? 50),
                'score' => (int)$analysis->score,
                'signal' => $analysis->signal,
            ] : null,
        ]);
    }

    /**
     * Format payload data untuk Apache ECharts
     */
    private function buildChartPayload(CryptoAsset $asset, string $timeframe): array
    {
        /** @var CryptoPrice[] $bars */
        $bars = CryptoPrice::find()
            ->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])
            ->orderBy(['bar_time' => SORT_ASC])
            ->limit(150)
            ->all();

        $dates = [];
        $candles = []; // [open, close, lowest, highest]
        $takerBuyVolumes = [];
        $takerSellVolumes = [];
        $ma20 = [];
        $ma50 = [];
        $rsiSeries = [];

        $closes = [];
        foreach ($bars as $i => $bar) {
            // Label tanggal: potong detik untuk kerapian
            $dates[] = substr($bar->bar_time, 5, 11); // e.g. "10-18 16:00"
            $candles[] = [
                (float)$bar->open,
                (float)$bar->close,
                (float)$bar->low,
                (float)$bar->high,
            ];
            $takerBuyVolumes[] = (float)$bar->buy_volume;
            $takerSellVolumes[] = (float)$bar->sell_volume;

            $closes[] = (float)$bar->close;

            // Hitung MA20 per bar
            if ($i >= 19) {
                $sum20 = array_sum(array_slice($closes, $i - 19, 20));
                $ma20[] = round($sum20 / 20, 4);
            } else {
                $ma20[] = '-';
            }

            // Hitung MA50 per bar
            if ($i >= 49) {
                $sum50 = array_sum(array_slice($closes, $i - 49, 50));
                $ma50[] = round($sum50 / 50, 4);
            } else {
                $ma50[] = '-';
            }
        }

        // Hitung RSI 14 per bar
        $rsiSeries = $this->buildRsiSeries($closes, 14);

        return [
            'dates' => $dates,
            'candles' => $candles,
            'takerBuyVolumes' => $takerBuyVolumes,
            'takerSellVolumes' => $takerSellVolumes,
            'ma20' => $ma20,
            'ma50' => $ma50,
            'rsi' => $rsiSeries,
        ];
    }

    private function buildRsiSeries(array $closes, int $period = 14): array
    {
        $n = count($closes);
        $result = [];
        if ($n <= $period) {
            return array_fill(0, $n, '-');
        }

        for ($k = 0; $k < $period; $k++) {
            $result[] = '-';
        }

        $gains = 0.0;
        $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            if ($change >= 0) {
                $gains += $change;
            } else {
                $losses += abs($change);
            }
        }
        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;
        $rs = $avgLoss == 0 ? 100 : ($avgGain / $avgLoss);
        $result[] = round(100 - (100 / (1 + $rs)), 2);

        for ($i = $period + 1; $i < $n; $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gain = $change >= 0 ? $change : 0.0;
            $loss = $change < 0 ? abs($change) : 0.0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
            $rs = $avgLoss == 0 ? 100 : ($avgGain / $avgLoss);
            $result[] = round(100 - (100 / (1 + $rs)), 2);
        }

        return $result;
    }

    /**
     * Scanner Confluence Multi-Indikator untuk Crypto (MA, RSI, MACD & Fibonacci)
     */
    public function actionConfluence(): string
    {
        $timeframe = Yii::$app->request->get('timeframe', CryptoPrice::TIMEFRAME_1H);
        if (!array_key_exists($timeframe, CryptoPrice::SUPPORTED_TIMEFRAMES)) {
            $timeframe = CryptoPrice::TIMEFRAME_1H;
        }

        $strategy = Yii::$app->request->get('strategy', 'all');
        $minScore = (int)Yii::$app->request->get('minScore', 0);
        $minRr = (float)Yii::$app->request->get('minRr', 0.0);
        $search = trim((string)Yii::$app->request->get('search', ''));
        $sort = Yii::$app->request->get('sort', 'score_desc');

        $confluenceService = new \app\services\crypto\CryptoConfluenceService();
        $confluenceService->init();

        $results = $confluenceService->scanAll([
            'timeframe' => $timeframe,
            'strategy' => $strategy,
            'minScore' => $minScore,
            'minRr' => $minRr,
            'search' => $search,
        ]);

        // Sorting
        usort($results, function ($a, $b) use ($sort) {
            return match ($sort) {
                'score_desc' => $b['confluence_score'] <=> $a['confluence_score'],
                'score_asc' => $a['confluence_score'] <=> $b['confluence_score'],
                'rr_desc' => $b['rr_ratio'] <=> $a['rr_ratio'],
                'upside_desc' => $b['potential_upside'] <=> $a['potential_upside'],
                'buy_ratio_desc' => $b['buy_ratio'] <=> $a['buy_ratio'],
                'name_asc' => strcmp($a['name'], $b['name']),
                default => $b['confluence_score'] <=> $a['confluence_score'],
            };
        });

        $stats = [
            'total' => count($results),
            'strongBuyCount' => count(array_filter($results, fn($r) => $r['action'] === \app\services\crypto\CryptoConfluenceService::ACTION_STRONG_BUY)),
            'buyCount' => count(array_filter($results, fn($r) => $r['action'] === \app\services\crypto\CryptoConfluenceService::ACTION_BUY)),
            'goldenPocketCount' => count(array_filter($results, fn($r) => !empty($r['in_golden_pocket']))),
            'macdCrossCount' => count(array_filter($results, fn($r) => ($r['macd_cross'] ?? '') === 'BULLISH_CROSS')),
            'oversoldCount' => count(array_filter($results, fn($r) => ($r['rsi_status'] ?? '') === 'OVERSOLD')),
        ];

        [$currency, $usdtIdrRate] = $this->resolveCurrency();

        return $this->render('confluence', [
            'timeframe' => $timeframe,
            'strategy' => $strategy,
            'minScore' => $minScore,
            'minRr' => $minRr,
            'search' => $search,
            'sort' => $sort,
            'results' => $results,
            'stats' => $stats,
            'presets' => \app\services\crypto\CryptoConfluenceService::getStrategyPresets(),
            'timeframes' => CryptoPrice::SUPPORTED_TIMEFRAMES,
            'currency' => $currency,
            'usdtIdrRate' => $usdtIdrRate,
        ]);
    }

    /**
     * Scanner Pola Bullish Continuation & Breakout untuk Crypto
     */
    public function actionBullishPatterns(): string
    {
        $timeframe = Yii::$app->request->get('timeframe', CryptoPrice::TIMEFRAME_1H);
        if (!array_key_exists($timeframe, CryptoPrice::SUPPORTED_TIMEFRAMES)) {
            $timeframe = CryptoPrice::TIMEFRAME_1H;
        }

        $patternType = Yii::$app->request->get('patternType', \app\services\crypto\CryptoBullishPatternService::PATTERN_ALL);
        $status = Yii::$app->request->get('status', '');
        $minConfidence = (int)Yii::$app->request->get('minConfidence', 0);
        $minRr = (float)Yii::$app->request->get('minRr', 0.0);
        $search = trim((string)Yii::$app->request->get('search', ''));
        $sort = Yii::$app->request->get('sort', 'confidence_desc');

        $patternService = new \app\services\crypto\CryptoBullishPatternService();
        $patternService->init();

        $results = $patternService->scanAll([
            'timeframe' => $timeframe,
            'patternType' => $patternType,
            'status' => $status,
            'minConfidence' => $minConfidence,
            'minRr' => $minRr,
            'search' => $search,
        ]);

        // Sorting
        usort($results, function ($a, $b) use ($sort) {
            return match ($sort) {
                'confidence_desc' => $b['confidence'] <=> $a['confidence'],
                'confidence_asc' => $a['confidence'] <=> $b['confidence'],
                'rr_desc' => $b['rr_ratio'] <=> $a['rr_ratio'],
                'upside_desc' => $b['potential_upside'] <=> $a['potential_upside'],
                'distance_asc' => abs($a['distance_to_breakout_pct']) <=> abs($b['distance_to_breakout_pct']),
                default => $b['confidence'] <=> $a['confidence'],
            };
        });

        $stats = [
            'total' => count($results),
            'buyZoneCount' => count(array_filter($results, fn($r) => $r['trade_status'] === \app\services\crypto\CryptoBullishPatternService::STATUS_BUY_ZONE)),
            'retestCount' => count(array_filter($results, fn($r) => $r['trade_status'] === \app\services\crypto\CryptoBullishPatternService::STATUS_RETEST)),
            'formingCount' => count(array_filter($results, fn($r) => $r['trade_status'] === \app\services\crypto\CryptoBullishPatternService::STATUS_FORMING)),
            'highConfidenceCount' => count(array_filter($results, fn($r) => $r['confidence'] >= 75)),
            'highRrCount' => count(array_filter($results, fn($r) => $r['rr_ratio'] >= 2.0)),
        ];

        [$currency, $usdtIdrRate] = $this->resolveCurrency();

        return $this->render('bullish-patterns', [
            'timeframe' => $timeframe,
            'patternType' => $patternType,
            'status' => $status,
            'minConfidence' => $minConfidence,
            'minRr' => $minRr,
            'search' => $search,
            'sort' => $sort,
            'results' => $results,
            'stats' => $stats,
            'patternTypes' => \app\services\crypto\CryptoBullishPatternService::getPatternTypes(),
            'statusOptions' => \app\services\crypto\CryptoBullishPatternService::getStatusOptions(),
            'timeframes' => CryptoPrice::SUPPORTED_TIMEFRAMES,
            'currency' => $currency,
            'usdtIdrRate' => $usdtIdrRate,
        ]);
    }

    /**
     * Resolves currency preference (IDR vs USDT) and fetches live USDT/IDR rate
     *
     * @return array{0: string, 1: float}
     */
    private function resolveCurrency(): array
    {
        $request = Yii::$app->request;
        $session = Yii::$app->session;

        $currency = $request->get('currency');
        if ($currency !== null) {
            $currency = strtoupper(trim((string)$currency));
            if (in_array($currency, ['IDR', 'USDT'], true)) {
                $session->set('crypto_preferred_currency', $currency);
            } else {
                $currency = 'IDR';
            }
        } else {
            $currency = $session->get('crypto_preferred_currency', 'IDR');
        }

        $provider = new \app\services\crypto\BinanceCryptoProvider();
        $provider->init();
        $rate = $provider->getUsdtIdrRate();

        return [$currency, $rate];
    }
}

