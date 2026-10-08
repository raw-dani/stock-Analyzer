<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Stock;
use app\services\MovingAverageService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller untuk Scanner & Sinyal Posisi Beli/Jual Moving Average.
 */
final class MovingAverageController extends Controller
{
    private MovingAverageService $service;

    public function __construct($id, $module, ?MovingAverageService $service = null, $config = [])
    {
        $this->service = $service ?? new MovingAverageService();
        parent::__construct($id, $module, $config);
    }

    /**
     * Halaman Utama: Scanner Moving Average & Sinyal Beli/Jual.
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
            'buyCount' => 0,
            'pullbackCount' => 0,
            'sellCount' => 0,
        ];

        foreach ($results as $r) {
            $sym = $r['symbol'];
            $symbols[] = $sym;
            $exch = !empty($r['exchange']) ? $r['exchange'] . ':' : '';
            $tvSymbols[] = $exch . $sym;

            if ($r['action'] === 'BUY') {
                $stats['buyCount']++;
            }
            if ($r['signal'] === MovingAverageService::SIGNAL_PULLBACK_BUY) {
                $stats['pullbackCount']++;
            }
            if ($r['action'] === 'SELL' || $r['action'] === 'TAKE_PROFIT') {
                $stats['sellCount']++;
            }
        }

        return $this->render('index', [
            'results' => $results,
            'params' => $params,
            'stats' => $stats,
            'symbols' => array_values(array_unique($symbols)),
            'tvSymbols' => array_values(array_unique($tvSymbols)),
            'maPairs' => MovingAverageService::getMaPairs(),
            'timeframes' => MovingAverageService::getTimeframes(),
        ]);
    }

    /**
     * Halaman Detail: Rencana Trading Presisi & Chart Candlestick MA.
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
            'lookback' => 150,
        ]);

        if ($analysis === null) {
            Yii::$app->session->setFlash('warning', "Data harga historis untuk {$symbol} belum mencukupi untuk kalkulasi Moving Average.");
            return $this->redirect(['index']);
        }

        return $this->render('detail', [
            'stock' => $stock,
            'analysis' => $analysis,
            'params' => $params,
            'maPairs' => MovingAverageService::getMaPairs(),
            'timeframes' => MovingAverageService::getTimeframes(),
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

        $filename = 'moving_average_signals_' . date('Ymd_His') . '.csv';
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Simbol',
            'Nama Perusahaan',
            'Bursa',
            'Sektor',
            'Harga Saat Ini',
            'Sinyal',
            'Rekomendasi Aksi',
            'Setup Strategi',
            'Posisi Beli (Entry)',
            'Titik Stop Loss',
            'Risiko Cut Loss (%)',
            'Target Profit 1 (TP1)',
            'Potensi Gain TP1 (%)',
            'Target Profit 2 (TP2)',
            'Potensi Gain TP2 (%)',
            'Risk/Reward Ratio',
            'Fast MA',
            'Slow MA',
            'RVOL',
            'Status Tren',
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
                $r['entry_price'],
                $r['stop_loss'],
                $r['stop_loss_pct'] . '%',
                $r['target_1'],
                '+' . $r['target_1_pct'] . '%',
                $r['target_2'],
                '+' . $r['target_2_pct'] . '%',
                $r['rr_ratio'] . 'x',
                $r['fast_ma'],
                $r['slow_ma'],
                $r['rvol'] . 'x',
                $r['trend_status'],
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
            'maPair' => (string) $req->get('maPair', MovingAverageService::PAIR_20_50),
            'strategy' => (string) $req->get('strategy', 'all'),
            'minRr' => (float) $req->get('minRr', 0.0),
            'exchange' => (string) $req->get('exchange', ''),
            'sector' => (string) $req->get('sector', ''),
            'symbol' => strtoupper((string) $req->get('symbol', '')),
            'timeframe' => (int) $req->get('timeframe', MovingAverageService::TIMEFRAME_1D),
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
                'price' => ($b['current_price'] <=> $a['current_price']),
                default => ($b['rr_ratio'] <=> $a['rr_ratio']),
            };
        });
        return $results;
    }
}
