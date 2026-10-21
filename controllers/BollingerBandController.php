<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Stock;
use app\services\BollingerBandService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller untuk Scanner & Sinyal Posisi Beli/Jual Bollinger Bands Strategy dengan Shakeout Low.
 */
final class BollingerBandController extends Controller
{
    private BollingerBandService $service;

    public function __construct($id, $module, ?BollingerBandService $service = null, $config = [])
    {
        $this->service = $service ?? new BollingerBandService();
        parent::__construct($id, $module, $config);
    }

    /**
     * Halaman Utama: Scanner Bollinger Bands, Statistik KPI, Filter Presets, & Tabel Sinyal.
     */
    public function actionIndex(): string
    {
        $params = $this->resolveParams();
        $cache = Yii::$app->cache;
        $cacheKey = 'bb_scan_' . md5(json_encode($params));

        $cachedData = $cache !== null ? $cache->get($cacheKey) : false;
        if ($cachedData !== false && is_array($cachedData)) {
            $results = $cachedData['results'];
            $symbols = $cachedData['symbols'];
            $tvSymbols = $cachedData['tvSymbols'];
            $stats = $cachedData['stats'];
        } else {
            $rawResults = $this->service->scanAll($params);
            $results = $this->sortResults($rawResults, $params['sort']);

            $symbols = [];
            $tvSymbols = [];
            $stats = [
                'total' => count($results),
                'buyCount' => 0,
                'shakeoutCount' => 0,
                'squeezeCount' => 0,
                'bounceCount' => 0,
                'sellCount' => 0,
            ];

            foreach ($results as $r) {
                $sym = $r['symbol'];
                $symbols[] = $sym;
                $exch = !empty($r['exchange']) ? $r['exchange'] . ':' : '';
                $tvSymbols[] = $exch . $sym;

                if (in_array($r['action'], [BollingerBandService::ACTION_BUY, BollingerBandService::ACTION_STRONG_BUY], true)) {
                    $stats['buyCount']++;
                }
                if (!empty($r['is_shakeout_low']) || ($r['signal'] ?? '') === BollingerBandService::SIGNAL_SHAKEOUT_BUY) {
                    $stats['shakeoutCount']++;
                }
                if (($r['signal'] ?? '') === BollingerBandService::SIGNAL_SQUEEZE_BREAKOUT || !empty($r['is_squeeze'])) {
                    $stats['squeezeCount']++;
                }
                if (($r['signal'] ?? '') === BollingerBandService::SIGNAL_LOWER_BOUNCE) {
                    $stats['bounceCount']++;
                }
                if (in_array($r['action'], [BollingerBandService::ACTION_SELL, BollingerBandService::ACTION_TAKE_PROFIT], true)) {
                    $stats['sellCount']++;
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
                ], 900); // 15 menit
            }
        }

        return $this->render('index', [
            'results' => $results,
            'params' => $params,
            'stats' => $stats,
            'symbols' => $symbols,
            'tvSymbols' => $tvSymbols,
            'timeframes' => BollingerBandService::getTimeframes(),
            'presets' => BollingerBandService::getStrategyPresets(),
        ]);
    }

    /**
     * Halaman Detail: Rencana Trading Presisi, Interactive Chart, & Kapan Waktu Beli/Jual.
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
            'period' => $params['period'],
            'multiplier' => $params['multiplier'],
            'lookback' => 80,
        ]);

        if ($analysis === null) {
            Yii::$app->session->setFlash('warning', "Data harga untuk {$symbol} belum mencukupi untuk analisis Bollinger Bands.");
            return $this->redirect(['index']);
        }

        return $this->render('detail', [
            'stock' => $stock,
            'analysis' => $analysis,
            'params' => $params,
            'timeframes' => BollingerBandService::getTimeframes(),
        ]);
    }

    /**
     * Export hasil scanner ke CSV.
     */
    public function actionExport(): Response
    {
        $params = $this->resolveParams();
        $results = $this->service->scanAll($params);
        $results = $this->sortResults($results, $params['sort']);

        $filename = 'bollinger_bands_scan_' . date('Ymd_His') . '.csv';
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Simbol',
            'Nama Perusahaan',
            'Bursa',
            'Sektor',
            'Harga Saat Ini',
            'Sinyal',
            'Aksi Rekomendasi',
            'Shakeout Low',
            'Setup Strategi',
            'Upper Band',
            'Middle Band (SMA 20)',
            'Lower Band',
            '%B',
            'BandWidth (%)',
            'Posisi Beli (Entry)',
            'Titik Stop Loss',
            'Risiko Cut Loss (%)',
            'Target Profit 1 (TP1)',
            'Potensi Gain TP1 (%)',
            'Target Profit 2 (TP2)',
            'Potensi Gain TP2 (%)',
            'Risk/Reward Ratio',
            'RVOL',
            'RSI 14',
        ]);

        foreach ($results as $r) {
            fputcsv($handle, [
                $r['symbol'],
                $r['name'] ?? '',
                $r['exchange'] ?? '',
                $r['sector'] ?? '',
                $r['price'],
                $r['signal'],
                $r['action'],
                !empty($r['is_shakeout_low']) ? 'YA (' . ($r['shakeout_undercut_pct'] ?? 0) . '%)' : 'TIDAK',
                $r['strategy_name'],
                $r['upper_band'],
                $r['middle_band'],
                $r['lower_band'],
                $r['percent_b'],
                $r['bandwidth'] . '%',
                $r['entry_price'],
                $r['stop_loss'],
                $r['stop_loss_pct'] . '%',
                $r['target_1'],
                '+' . $r['target_1_pct'] . '%',
                $r['target_2'],
                '+' . $r['target_2_pct'] . '%',
                $r['rr_ratio'] . 'x',
                $r['rvol'] . 'x',
                $r['rsi'],
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

    private function resolveParams(): array
    {
        $req = Yii::$app->request;
        return [
            'strategy' => (string) $req->get('strategy', 'all'),
            'minRr' => (float) $req->get('minRr', 0.0),
            'exchange' => (string) $req->get('exchange', ''),
            'sector' => (string) $req->get('sector', ''),
            'symbol' => strtoupper((string) $req->get('symbol', '')),
            'timeframe' => (int) $req->get('timeframe', BollingerBandService::TIMEFRAME_1D),
            'period' => (int) $req->get('period', 20),
            'multiplier' => (float) $req->get('multiplier', 2.0),
            'sort' => (string) $req->get('sort', 'rr'),
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
                'rvol' => ($b['rvol'] <=> $a['rvol']),
                'symbol' => ($a['symbol'] <=> $b['symbol']),
                'price' => ($b['price'] <=> $a['price']),
                'bandwidth' => ($a['bandwidth'] <=> $b['bandwidth']),
                default => ($b['rr_ratio'] <=> $a['rr_ratio']),
            };
        });
        return $results;
    }
}
