<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Stock;
use app\services\MultiIndicatorStrategyService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller untuk Scanner & Analisis Confluence Multi-Indikator & Multi-Timeframe:
 * - Support & Resistance / Moving Average (SMA & EMA)
 * - RSI (Relative Strength Index) Wilder 14
 * - MACD (12, 26, 9)
 * - Fibonacci Retracement (Golden Pocket 61.8%)
 * - Multi-Timeframe Trend vs Entry Precision (1D / 4H / 2H / 1H)
 */
final class StrategyConfluenceController extends Controller
{
    private MultiIndicatorStrategyService $service;

    public function __construct($id, $module, ?MultiIndicatorStrategyService $service = null, $config = [])
    {
        $this->service = $service ?? new MultiIndicatorStrategyService();
        parent::__construct($id, $module, $config);
    }

    /**
     * Halaman Scanner Confluence Multi-Indikator.
     */
    public function actionIndex(): string
    {
        $params = $this->resolveParams();
        $cache = Yii::$app->cache;
        $cacheKey = \app\services\ScannerCacheService::buildCacheKey('confluence_scan', $params, (int) $params['timeframe']);

        $cachedData = $cache !== null ? $cache->get($cacheKey) : false;
        $fromCache = false;

        if ($cachedData !== false && is_array($cachedData)) {
            $results = $cachedData['results'];
            $symbols = $cachedData['symbols'];
            $tvSymbols = $cachedData['tvSymbols'];
            $stats = $cachedData['stats'];
            $fromCache = true;
        } else {
            $rawResults = $this->service->scanAll($params);
            $results = $this->sortResults($rawResults, $params['sort']);

            $symbols = [];
            $tvSymbols = [];
            $stats = [
                'total' => count($results),
                'strongBuyCount' => 0,
                'buyCount' => 0,
                'goldenPocketCount' => 0,
                'shakeoutCount' => 0,
                'macdCrossCount' => 0,
                'oversoldCount' => 0,
            ];

            foreach ($results as $r) {
                $sym = $r['symbol'];
                $symbols[] = $sym;
                $exch = !empty($r['exchange']) ? $r['exchange'] . ':' : '';
                $tvSymbols[] = $exch . $sym;

                if ($r['action'] === MultiIndicatorStrategyService::ACTION_STRONG_BUY) {
                    $stats['strongBuyCount']++;
                }
                if ($r['action'] === MultiIndicatorStrategyService::ACTION_BUY) {
                    $stats['buyCount']++;
                }
                if (!empty($r['is_shakeout_low'])) {
                    $stats['shakeoutCount']++;
                }
                if (!empty($r['in_golden_pocket'])) {
                    $stats['goldenPocketCount']++;
                }
                if (($r['macd_cross'] ?? '') === 'BULLISH_CROSS') {
                    $stats['macdCrossCount']++;
                }
                if (($r['rsi_status'] ?? '') === 'OVERSOLD') {
                    $stats['oversoldCount']++;
                }
            }

            $symbols = array_values(array_unique($symbols));
            $tvSymbols = array_values(array_unique($tvSymbols));

            if ($cache !== null) {
                $cache->set($cacheKey, [
                    'results' => $results,
                    'symbols' => $symbols,
                    'tvSymbols' => $tvSymbols,
                    'stats' => $stats,
                ], \app\services\ScannerCacheService::DEFAULT_TTL);
            }
        }

        return $this->render('index', [
            'results' => $results,
            'params' => $params,
            'stats' => $stats,
            'fromCache' => $fromCache,
            'symbols' => $symbols,
            'tvSymbols' => $tvSymbols,
            'timeframes' => MultiIndicatorStrategyService::getTimeframes(),
            'presets' => MultiIndicatorStrategyService::getStrategyPresets(),
        ]);
    }

    /**
     * Halaman Detail: Multi-Panel Charts & Rencana Trading Presisi Multi-Indikator.
     */
    public function actionDetail(string $symbol): Response|string
    {
        $stock = Stock::find()->where(['symbol' => strtoupper(trim($symbol)), 'active' => true])->one();
        if ($stock === null) {
            throw new NotFoundHttpException("Saham dengan simbol {$symbol} tidak ditemukan.");
        }

        $params = $this->resolveParams();
        $analysis = $this->service->analyzeStock((int) $stock->id, [
            'timeframe' => $params['timeframe'],
            'lookback' => 120,
        ]);

        if ($analysis === null) {
            Yii::$app->session->setFlash('warning', "Data harga historis untuk {$symbol} belum mencukupi untuk analisis Confluence Multi-Indikator.");
            return $this->redirect(['index']);
        }

        return $this->render('detail', [
            'stock' => $stock,
            'analysis' => $analysis,
            'params' => $params,
            'timeframes' => MultiIndicatorStrategyService::getTimeframes(),
        ]);
    }

    /**
     * Ekspor hasil scanner ke file CSV.
     */
    public function actionExport(): Response
    {
        $params = $this->resolveParams();
        $results = $this->service->scanAll($params);
        $results = $this->sortResults($results, $params['sort']);

        $filename = 'multi_indicator_confluence_signals_' . date('Ymd_His') . '.csv';
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Simbol',
            'Nama Perusahaan',
            'Bursa',
            'Sektor',
            'Harga Saat Ini',
            'Skor Konfluensi (0-100)',
            'Sinyal Rekomendasi',
            'Aksi',
            'Tren TF Harian',
            'RSI (14)',
            'MACD Line',
            'MACD Signal',
            'Status MACD',
            'Retracement Fib (%)',
            'Support Dinamis',
            'Resistance Dinamis',
            'Area Beli Presisi (Entry)',
            'Titik Stop Loss',
            'Risiko Cut Loss (%)',
            'Target Profit 1 (TP1)',
            'Potensi Gain TP1 (%)',
            'Target Profit 2 (TP2)',
            'Potensi Gain TP2 (%)',
            'Risk/Reward Ratio',
            'RVOL',
        ]);

        foreach ($results as $r) {
            fputcsv($handle, [
                $r['symbol'],
                $r['name'] ?? '',
                $r['exchange'] ?? '',
                $r['sector'] ?? '',
                $r['current_price'],
                $r['confluence_score'] . '/100',
                $r['signal_title'],
                $r['action'],
                $r['higher_tf_trend'],
                $r['rsi'],
                $r['macd'],
                $r['macd_signal'],
                $r['macd_cross'],
                $r['retrace_pct'] . '%',
                $r['support'],
                $r['resistance'],
                $r['entry_range'],
                $r['stop_loss'],
                $r['stop_loss_pct'] . '%',
                $r['target_1'],
                '+' . $r['target_1_pct'] . '%',
                $r['target_2'],
                '+' . $r['target_2_pct'] . '%',
                $r['rr_ratio'] . 'x',
                $r['rvol'] . 'x',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Yii::$app->response->sendContentAsFile($content, $filename, [
            'mimeType' => 'text/csv',
            'inline' => false,
        ]);
    }

    /**
     * Resolusi parameter filter request GET.
     */
    private function resolveParams(): array
    {
        $req = Yii::$app->request;
        return [
            'strategy' => (string) $req->get('strategy', 'all'),
            'timeframe' => (int) $req->get('timeframe', MultiIndicatorStrategyService::TIMEFRAME_1D),
            'minScore' => (int) $req->get('minScore', 0),
            'minRr' => (float) $req->get('minRr', 0.0),
            'exchange' => (string) $req->get('exchange', ''),
            'sector' => (string) $req->get('sector', ''),
            'symbol' => strtoupper((string) $req->get('symbol', '')),
            'sort' => (string) $req->get('sort', 'score'),
        ];
    }

    /**
     * Urutkan hasil scanner.
     */
    private function sortResults(array $results, string $sort): array
    {
        usort($results, function ($a, $b) use ($sort) {
            return match ($sort) {
                'gain' => ($b['target_1_pct'] <=> $a['target_1_pct']),
                'rr' => ($b['rr_ratio'] <=> $a['rr_ratio']),
                'rsi' => ($a['rsi'] <=> $b['rsi']),
                'rvol' => ($b['rvol'] <=> $a['rvol']),
                'symbol' => ($a['symbol'] <=> $b['symbol']),
                'price' => ($b['current_price'] <=> $a['current_price']),
                default => ($b['confluence_score'] <=> $a['confluence_score']),
            };
        });
        return $results;
    }
}
