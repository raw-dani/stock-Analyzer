<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Stock;
use app\services\CandlestickService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller untuk Scanner & Sinyal Pola Candlestick Paling Potensial dengan Konfirmasi Volume.
 *
 * Menganalisa pola-pola potensial (Hammer, Shooting Star, Engulfing, Doji, Marubozu, Morning/Evening Star)
 * untuk menentukan waktu beli dan jual yang tepat pada time frame multi-dimensi (1D, 4H, 2H, 1H)
 * serta mengonfirmasi apakah volume mendukung kenaikan harga saham.
 */
final class CandlestickController extends Controller
{
    private CandlestickService $service;

    public function __construct($id, $module, ?CandlestickService $service = null, $config = [])
    {
        $this->service = $service ?? new CandlestickService();
        parent::__construct($id, $module, $config);
    }

    /**
     * Halaman Utama: Scanner Pola Candlestick & Konfirmasi Volume.
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
            'surgeCount' => 0,
            'supportiveCount' => 0,
            'hammerCount' => 0,
            'engulfingCount' => 0,
            'marubozuCount' => 0,
            'dojiCount' => 0,
            'sellCount' => 0,
        ];

        foreach ($results as $r) {
            $sym = $r['symbol'];
            $symbols[] = $sym;
            $exch = !empty($r['exchange']) ? $r['exchange'] . ':' : '';
            $tvSymbols[] = $exch . $sym;

            if (in_array($r['action'], [CandlestickService::ACTION_BUY, CandlestickService::ACTION_STRONG_BUY], true)) {
                $stats['buyCount']++;
            }
            if ($r['volume_status'] === CandlestickService::VOL_SURGE) {
                $stats['surgeCount']++;
            }
            if (!empty($r['volume_supports_bullish'])) {
                $stats['supportiveCount']++;
            }
            if (in_array($r['pattern_code'], [CandlestickService::PATTERN_HAMMER, CandlestickService::PATTERN_INVERTED_HAMMER], true)) {
                $stats['hammerCount']++;
            }
            if (in_array($r['pattern_code'], [CandlestickService::PATTERN_BULLISH_ENGULFING, CandlestickService::PATTERN_BEARISH_ENGULFING], true)) {
                $stats['engulfingCount']++;
            }
            if (in_array($r['pattern_code'], [CandlestickService::PATTERN_BULLISH_MARUBOZU, CandlestickService::PATTERN_BEARISH_MARUBOZU], true)) {
                $stats['marubozuCount']++;
            }
            if ($r['pattern_code'] === CandlestickService::PATTERN_DOJI) {
                $stats['dojiCount']++;
            }
            if (in_array($r['action'], [CandlestickService::ACTION_SELL, CandlestickService::ACTION_STRONG_SELL], true)) {
                $stats['sellCount']++;
            }
        }

        // Ambil daftar sektor unik untuk dropdown
        $sectors = Stock::find()
            ->select('sector')
            ->distinct()
            ->where(['and', ['active' => true], ['not', ['sector' => null]], ['!=', 'sector', '']])
            ->orderBy('sector')
            ->column();

        return $this->render('index', [
            'results' => $results,
            'params' => $params,
            'stats' => $stats,
            'symbols' => array_values(array_unique($symbols)),
            'tvSymbols' => array_values(array_unique($tvSymbols)),
            'timeframes' => CandlestickService::getTimeframes(),
            'patterns' => CandlestickService::getPatterns(),
            'sectors' => $sectors,
        ]);
    }

    /**
     * Halaman Detail: Rencana Trading Presisi & Chart Candlestick + Volume Interaktif.
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
            'lookback' => 60,
            'allow_neutral' => true,
        ]);

        if ($analysis === null) {
            Yii::$app->session->setFlash('warning', "Data harga historis untuk {$symbol} belum mencukupi untuk analisa candlestick.");
            return $this->redirect(['index', 'timeframe' => $params['timeframe']]);
        }

        return $this->render('detail', [
            'stock' => $stock,
            'analysis' => $analysis,
            'params' => $params,
            'timeframes' => CandlestickService::getTimeframes(),
            'patterns' => CandlestickService::getPatterns(),
        ]);
    }

    /**
     * Ekspor hasil scanner candlestick ke format CSV.
     */
    public function actionExport(): Response
    {
        $params = $this->resolveParams();
        $results = $this->service->scanAll($params);
        $results = $this->sortResults($results, $params['sort']);

        $csv = "Simbol,Nama Perusahaan,Sektor,Exchange,Pola Candlestick,Sinyal/Aksi,Harga Terakhir,Chg %,RVOL,Status Volume,Mendukung Kenaikan?,Entry Price,Stop Loss,SL %,TP1,TP1 %,TP2,TP2 %,R:R Ratio,Skor Keyakinan,Waktu Rekomendasi\n";

        foreach ($results as $r) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%.2f,%.2f,%.2f,\"%s\",\"%s\",%.2f,%.2f,%.2f,%.2f,%.2f,%.2f,%.2f,%.1f,%d,\"%s\"\n",
                $r['symbol'],
                str_replace('"', '""', (string) $r['name']),
                str_replace('"', '""', (string) $r['sector']),
                $r['exchange'],
                $r['pattern_name'],
                $r['action'],
                $r['last_price'],
                $r['price_change_pct'],
                $r['rvol'],
                $r['volume_strength_label'],
                $r['volume_supports_bullish'] ? 'YA' : 'TIDAK',
                $r['entry_price'],
                $r['stop_loss'],
                $r['stop_loss_pct'],
                $r['target_1'],
                $r['target_1_pct'],
                $r['target_2'],
                $r['target_2_pct'],
                $r['rr_ratio'],
                $r['confidence_score'],
                str_replace('"', '""', (string) $r['timing_advice'])
            );
        }

        $filename = 'candlestick_scan_' . date('Ymd_His') . '.csv';
        return Yii::$app->response->sendContentAsFile($csv, $filename, ['mimeType' => 'text/csv']);
    }

    /**
     * Parsing dan sanitasi parameter query filter.
     *
     * @return array<string, mixed>
     */
    private function resolveParams(): array
    {
        $request = Yii::$app->request;

        $timeframe = (int) $request->get('timeframe', CandlestickService::TIMEFRAME_1D);
        if (!array_key_exists($timeframe, CandlestickService::getTimeframes())) {
            $timeframe = CandlestickService::TIMEFRAME_1D;
        }

        $preset = trim((string) $request->get('preset', ''));
        $pattern = trim((string) $request->get('pattern', 'all'));
        $volumeFilter = trim((string) $request->get('volume_filter', 'all'));
        $action = trim((string) $request->get('action', 'all'));

        // Handle Quick Presets
        if ($preset === 'buy_hammer') {
            $pattern = CandlestickService::PATTERN_HAMMER;
            $action = 'buy_only';
        } elseif ($preset === 'buy_engulfing') {
            $pattern = CandlestickService::PATTERN_BULLISH_ENGULFING;
            $action = 'buy_only';
        } elseif ($preset === 'momentum_marubozu') {
            $pattern = CandlestickService::PATTERN_BULLISH_MARUBOZU;
            $action = 'buy_only';
        } elseif ($preset === 'volume_surge') {
            $volumeFilter = 'surge_only';
            $action = 'buy_only';
        } elseif ($preset === 'supportive_vol') {
            $volumeFilter = 'supportive_only';
        } elseif ($preset === 'doji_watch') {
            $pattern = CandlestickService::PATTERN_DOJI;
        } elseif ($preset === 'sell_alerts') {
            $action = 'sell_only';
        }

        return [
            'timeframe' => $timeframe,
            'preset' => $preset,
            'pattern' => $pattern,
            'volume_filter' => $volumeFilter,
            'action' => $action,
            'symbol' => trim((string) $request->get('symbol', '')),
            'sector' => trim((string) $request->get('sector', '')),
            'exchange' => trim((string) $request->get('exchange', '')),
            'sort' => trim((string) $request->get('sort', 'confidence_desc')),
        ];
    }

    /**
     * Pengurutan hasil scan secara fleksibel.
     *
     * @param array<int, array<string, mixed>> $results
     * @param string $sort
     * @return array<int, array<string, mixed>>
     */
    private function sortResults(array $results, string $sort): array
    {
        usort($results, function ($a, $b) use ($sort) {
            return match ($sort) {
                'rvol_desc' => $b['rvol'] <=> $a['rvol'],
                'change_desc' => $b['price_change_pct'] <=> $a['price_change_pct'],
                'change_asc' => $a['price_change_pct'] <=> $b['price_change_pct'],
                'rr_desc' => $b['rr_ratio'] <=> $a['rr_ratio'],
                'symbol_asc' => strcmp($a['symbol'], $b['symbol']),
                default => $b['confidence_score'] <=> $a['confidence_score'], // confidence_desc
            };
        });

        return $results;
    }
}
