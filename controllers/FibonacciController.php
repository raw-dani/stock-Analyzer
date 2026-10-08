<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Stock;
use app\services\FibonacciService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller untuk Scanner & Sinyal Posisi Beli/Jual Fibonacci Retracement & Extension.
 *
 * Mendukung time frame multi-dimensi: 1D, 4H, 2H, 1H.
 * Menghitung Golden Pocket (50%-61.8%) untuk waktu beli terbaik,
 * dan Ekstensi 1.272 & 1.618 untuk waktu jual terbaik (Take Profit / Target).
 */
final class FibonacciController extends Controller
{
    private FibonacciService $service;

    public function __construct($id, $module, ?FibonacciService $service = null, $config = [])
    {
        $this->service = $service ?? new FibonacciService();
        parent::__construct($id, $module, $config);
    }

    /**
     * Halaman Utama: Scanner Fibonacci Retracement & Extension.
     */
    public function actionIndex(): string
    {
        $params = $this->resolveParams();
        $results = $this->service->scanAll($params);
        $results = $this->sortResults($results, $params['sort']);

        $symbols = [];
        $tvSymbols = [];
        $stats = [
            'total' => count($results),
            'goldenPocketCount' => 0,
            'buyCount' => 0,
            'targetReachedCount' => 0,
            'breakdownCount' => 0,
        ];

        foreach ($results as $r) {
            $sym = $r['symbol'];
            $symbols[] = $sym;
            $exch = !empty($r['exchange']) ? $r['exchange'] . ':' : '';
            $tvSymbols[] = $exch . $sym;

            if ($r['signal'] === FibonacciService::SIGNAL_GOLDEN_POCKET) {
                $stats['goldenPocketCount']++;
            }
            if ($r['action'] === 'BUY') {
                $stats['buyCount']++;
            }
            if ($r['signal'] === FibonacciService::SIGNAL_TARGET_REACHED) {
                $stats['targetReachedCount']++;
            }
            if ($r['signal'] === FibonacciService::SIGNAL_FIB_BREAKDOWN) {
                $stats['breakdownCount']++;
            }
        }

        return $this->render('index', [
            'results' => $results,
            'params' => $params,
            'stats' => $stats,
            'symbols' => array_values(array_unique($symbols)),
            'tvSymbols' => array_values(array_unique($tvSymbols)),
            'timeframes' => FibonacciService::getTimeframes(),
        ]);
    }

    /**
     * Halaman Detail: Rencana Trading Presisi & Chart Candlestick Fibonacci Interaktif.
     */
    public function actionDetail(string $symbol): string
    {
        $stock = Stock::find()->where(['symbol' => strtoupper(trim($symbol)), 'active' => true])->one();
        if ($stock === null) {
            throw new NotFoundHttpException("Saham dengan simbol {$symbol} tidak ditemukan.");
        }

        $params = $this->resolveParams();
        $analysis = $this->service->analyzeStock((int) $stock->id, [
            'timeframe' => $params['timeframe'],
            'lookback' => 100,
        ]);

        if ($analysis === null) {
            Yii::$app->session->setFlash('warning', "Data harga historis untuk {$symbol} belum mencukupi untuk kalkulasi level Fibonacci.");
            return $this->redirect(['index']);
        }

        return $this->render('detail', [
            'stock' => $stock,
            'analysis' => $analysis,
            'params' => $params,
            'timeframes' => FibonacciService::getTimeframes(),
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

        $filename = 'fibonacci_trade_signals_' . date('Ymd_His') . '.csv';
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Simbol',
            'Nama Perusahaan',
            'Bursa',
            'Sektor',
            'Harga Saat Ini',
            'Sinyal Fibonacci',
            'Aksi Rekomendasi',
            'Setup Strategi',
            'Rekomendasi Timing',
            'Swing High (0.0%)',
            'Swing Low (100.0%)',
            'Retracement (%)',
            'Area Beli (Entry Range)',
            'Titik Stop Loss',
            'Risiko Cut Loss (%)',
            'Target Profit 1 (TP1 - Swing High)',
            'Potensi Gain TP1 (%)',
            'Target Profit 2 (TP2 - 1.272 Ext)',
            'Potensi Gain TP2 (%)',
            'Target Profit 3 (TP3 - 1.618 Ext)',
            'Potensi Gain TP3 (%)',
            'Risk/Reward Ratio',
            'RVOL',
            'Tanggal Analisis',
        ]);

        foreach ($results as $r) {
            fputcsv($handle, [
                $r['symbol'],
                $r['name'] ?? '',
                $r['exchange'] ?? '',
                $r['sector'] ?? '',
                $r['current_price'],
                $r['signal'],
                $r['action'],
                $r['strategy_name'],
                $r['timing_recommendation'],
                $r['swing_high'],
                $r['swing_low'],
                $r['retrace_pct'] . '%',
                $r['entry_range'],
                $r['stop_loss'],
                $r['stop_loss_pct'] . '%',
                $r['target_1'],
                '+' . $r['target_1_pct'] . '%',
                $r['target_2'],
                '+' . $r['target_2_pct'] . '%',
                $r['target_3'],
                '+' . $r['target_3_pct'] . '%',
                $r['rr_ratio'] . 'x',
                $r['rvol'] . 'x',
                $r['date'] ?? '',
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
            'timeframe' => (int) $req->get('timeframe', FibonacciService::TIMEFRAME_1D),
            'sort' => (string) $req->get('sort', 'rr'),
        ];
    }

    /**
     * Urutkan hasil scanner Fibonacci.
     */
    private function sortResults(array $results, string $sort): array
    {
        usort($results, function ($a, $b) use ($sort) {
            return match ($sort) {
                'gain' => ($b['target_1_pct'] <=> $a['target_1_pct']),
                'retrace' => ($a['retrace_pct'] <=> $b['retrace_pct']),
                'rvol' => ($b['rvol'] <=> $a['rvol']),
                'symbol' => ($a['symbol'] <=> $b['symbol']),
                'price' => ($b['current_price'] <=> $a['current_price']),
                default => ($b['rr_ratio'] <=> $a['rr_ratio']),
            };
        });
        return $results;
    }
}
