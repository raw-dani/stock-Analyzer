<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Stock;
use app\services\StochasticService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controller untuk Scanner & Trading Plan Stochastic Oscillator Strategy.
 */
final class StochasticController extends Controller
{
    private StochasticService $stochasticService;

    public function __construct($id, $module, ?StochasticService $stochasticService = null, $config = [])
    {
        $this->stochasticService = $stochasticService ?? new StochasticService();
        parent::__construct($id, $module, $config);
    }

    /**
     * Halaman Scanner Utama Stochastic Oscillator.
     */
    public function actionIndex(): string
    {
        $request = Yii::$app->request;

        $params = [
            'strategy'     => $request->get('strategy', 'all'),
            'timeframe'    => (int) $request->get('timeframe', StochasticService::TIMEFRAME_1D),
            'minRr'        => (float) $request->get('minRr', 0.0),
            'exchange'     => $request->get('exchange', ''),
            'sector'       => $request->get('sector', 'all'),
            'symbol'       => $request->get('symbol', ''),
            'sort'         => $request->get('sort', 'confidence_desc'),
            'periodK'      => (int) $request->get('periodK', 14),
            'smoothK'      => (int) $request->get('smoothK', 3),
            'periodD'      => (int) $request->get('periodD', 3),
            'lookback'     => (int) $request->get('lookback', 80),
        ];

        // Jalankan scan dengan cache berbasis versi data pasar
        $cacheArgs = array_values($params);
        $cacheKey = \app\services\ScannerCacheService::buildCacheKey('stochastic', $cacheArgs, $params['timeframe']);
        $cached = Yii::$app->cache ? Yii::$app->cache->get($cacheKey) : false;

        if (is_array($cached)) {
            $results = $cached;
        } else {
            $results = $this->stochasticService->scanAll($params);
            if (Yii::$app->cache) {
                Yii::$app->cache->set($cacheKey, $results, \app\services\ScannerCacheService::DEFAULT_TTL);
            }
        }

        // Sorting
        $sort = $params['sort'];
        usort($results, function ($a, $b) use ($sort) {
            return match ($sort) {
                'quick_score' => ($b['quick_rebound_score'] ?? 0) <=> ($a['quick_rebound_score'] ?? 0),
                'rr_desc' => ($b['risk_reward_ratio'] ?? 0) <=> ($a['risk_reward_ratio'] ?? 0),
                'k_asc' => ($a['curr_k'] ?? 0) <=> ($b['curr_k'] ?? 0),
                'k_desc' => ($b['curr_k'] ?? 0) <=> ($a['curr_k'] ?? 0),
                'price_desc' => ($b['current_price'] ?? 0) <=> ($a['current_price'] ?? 0),
                'price_asc' => ($a['current_price'] ?? 0) <=> ($b['current_price'] ?? 0),
                'change_desc' => ($b['change_pct'] ?? 0) <=> ($a['change_pct'] ?? 0),
                'symbol_asc' => strcmp($a['symbol'], $b['symbol']),
                default => ($b['confidence_score'] ?? 0) <=> ($a['confidence_score'] ?? 0),
            };
        });

        // Hitung statistik ringkasan
        $stats = [
            'total_scanned' => count($results),
            'quick_3pct_count' => 0,
            'shakeout_low_count' => 0,
            'golden_cross_oversold' => 0,
            'bullish_divergence' => 0,
            'total_buy' => 0,
            'total_sell' => 0,
            'oversold_count' => 0,
            'overbought_count' => 0,
        ];

        foreach ($results as $r) {
            if (!empty($r['is_quick_rebound'])) {
                $stats['quick_3pct_count']++;
            }
            if (in_array($r['action'], [StochasticService::ACTION_STRONG_BUY, StochasticService::ACTION_BUY], true)) {
                $stats['total_buy']++;
            }
            if (in_array($r['action'], [StochasticService::ACTION_SELL, StochasticService::ACTION_TAKE_PROFIT], true)) {
                $stats['total_sell']++;
            }
            if ($r['signal'] === StochasticService::SIGNAL_SHAKEOUT_LOW || (!empty($r['shakeout_low']['detected']))) {
                $stats['shakeout_low_count']++;
            }
            if ($r['signal'] === StochasticService::SIGNAL_GOLDEN_CROSS_OVERSOLD) {
                $stats['golden_cross_oversold']++;
            }
            if ($r['signal'] === StochasticService::SIGNAL_BULLISH_DIVERGENCE || $r['signal'] === StochasticService::SIGNAL_HIDDEN_BULLISH_DIV) {
                $stats['bullish_divergence']++;
            }
            if (!empty($r['is_oversold'])) {
                $stats['oversold_count']++;
            }
            if (!empty($r['is_overbought'])) {
                $stats['overbought_count']++;
            }
        }

        // Ambil daftar sektor unik
        $sectors = Stock::find()
            ->select('sector')
            ->where(['active' => true])
            ->andWhere(['not', ['sector' => null]])
            ->andWhere(['!=', 'sector', ''])
            ->distinct()
            ->column();
        sort($sectors);

        $symbols = [];
        $tvSymbols = [];
        foreach ($results as $r) {
            $sym = $r['symbol'];
            $symbols[] = $sym;
            $exch = !empty($r['exchange']) ? $r['exchange'] . ':' : '';
            $tvSymbols[] = $exch . $sym;
        }

        return $this->render('index', [
            'results' => $results,
            'params' => $params,
            'stats' => $stats,
            'sectors' => $sectors,
            'symbols' => $symbols,
            'tvSymbols' => $tvSymbols,
            'fromCache' => true,
            'timeframes' => StochasticService::getTimeframes(),
            'presets' => StochasticService::getStrategyPresets(),
        ]);
    }

    /**
     * Ekspor hasil scanner Stochastic Oscillator ke format file CSV.
     */
    public function actionExport()
    {
        $request = Yii::$app->request;
        $params = [
            'strategy'     => $request->get('strategy', 'all'),
            'timeframe'    => (int) $request->get('timeframe', StochasticService::TIMEFRAME_1D),
            'minRr'        => (float) $request->get('minRr', 0.0),
            'exchange'     => $request->get('exchange', ''),
            'sector'       => $request->get('sector', 'all'),
            'symbol'       => $request->get('symbol', ''),
            'sort'         => $request->get('sort', 'confidence_desc'),
            'periodK'      => (int) $request->get('periodK', 14),
            'smoothK'      => (int) $request->get('smoothK', 3),
            'periodD'      => (int) $request->get('periodD', 3),
            'lookback'     => (int) $request->get('lookback', 80),
        ];

        $results = $this->stochasticService->scanAll($params);
        $filename = 'stochastic_scanner_' . date('Ymd_His') . '.csv';

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'Symbol',
            'Name',
            'Sector',
            'Price',
            'Change %',
            '%K',
            '%D',
            'Zone',
            'Signal',
            'Strategy',
            'Confidence Score',
            'Entry Price',
            'Stop Loss',
            'Stop Loss %',
            'TP 1',
            'TP 1 %',
            'TP 2',
            'TP 2 %',
            'R:R Ratio',
            'Action',
        ]);

        foreach ($results as $r) {
            fputcsv($handle, [
                $r['symbol'],
                $r['name'],
                $r['sector'] ?? '',
                $r['current_price'],
                $r['change_pct'] . '%',
                $r['curr_k'],
                $r['curr_d'],
                $r['zone'],
                $r['signal'],
                $r['strategy_name'],
                $r['confidence_score'] . '%',
                $r['entry_price'],
                $r['stop_loss'],
                $r['stop_loss_pct'] . '%',
                $r['target_profit_1'],
                '+' . $r['target_profit_1_pct'] . '%',
                $r['target_profit_2'],
                '+' . $r['target_profit_2_pct'] . '%',
                '1:' . $r['risk_reward_ratio'],
                $r['action'],
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
     * Halaman Detail & Interactive Chart Stochastic Trading Plan.
     */
    public function actionDetail(?string $symbol = null): string
    {
        $symbol = $symbol ?? Yii::$app->request->get('symbol');
        if (empty($symbol)) {
            throw new NotFoundHttpException("Parameter symbol saham diperlukan.");
        }
        $symbol = strtoupper(trim((string) $symbol));
        $stock = Stock::findOne(['symbol' => $symbol]);
        if ($stock === null) {
            throw new NotFoundHttpException("Saham {$symbol} tidak ditemukan di database.");
        }

        $request = Yii::$app->request;
        $params = [
            'timeframe' => (int) $request->get('timeframe', StochasticService::TIMEFRAME_1D),
            'periodK'   => (int) $request->get('periodK', 14),
            'smoothK'   => (int) $request->get('smoothK', 3),
            'periodD'   => (int) $request->get('periodD', 3),
            'lookback'  => (int) $request->get('lookback', 80),
        ];

        $runOptimize = (bool) $request->get('optimize', false);
        $runBacktest = (bool) $request->get('backtest', false) || $runOptimize;
        $backtestResult = null;
        $optimizeResult = null;

        if ($runBacktest) {
            $btLookback = (int) $request->get('btLookback', 300);
            $btFeePct = (float) $request->get('btFee', 0.30);
            $btSlippage = (float) $request->get('btSlippage', 0.10);
            $btTrendFilter = (bool) $request->get('btTrendFilter', 1);
            $btCandleConfirm = (bool) $request->get('btCandleConfirm', 1);
            $btTpModel = (string) $request->get('btTpModel', $params['timeframe'] === StochasticService::TIMEFRAME_1D ? 'standard' : 'adaptive');

            $backtestService = new \app\services\StochasticBacktestService();

            if ($runOptimize) {
                $optimizeResult = $backtestService->optimizeParameters($stock, [
                    'timeframe' => $params['timeframe'],
                    'lookbackCandles' => $btLookback,
                    'feePct' => $btFeePct,
                    'slippagePct' => $btSlippage,
                    'trendFilter' => $btTrendFilter,
                    'candleConfirmation' => $btCandleConfirm,
                    'tpModel' => $btTpModel,
                ]);
                if (!empty($optimizeResult['success']) && !empty($optimizeResult['best'])) {
                    $params['periodK'] = (int) $optimizeResult['best']['periodK'];
                    $params['smoothK'] = (int) $optimizeResult['best']['smoothK'];
                    $params['periodD'] = (int) $optimizeResult['best']['periodD'];
                    $backtestResult = $optimizeResult['best']['backtest'];
                } else {
                    $backtestResult = $optimizeResult;
                }
            } else {
                $backtestResult = $backtestService->runStockBacktest($stock, [
                    'timeframe' => $params['timeframe'],
                    'periodK' => $params['periodK'],
                    'smoothK' => $params['smoothK'],
                    'periodD' => $params['periodD'],
                    'lookbackCandles' => $btLookback,
                    'feePct' => $btFeePct,
                    'slippagePct' => $btSlippage,
                    'trendFilter' => $btTrendFilter,
                    'candleConfirmation' => $btCandleConfirm,
                    'tpModel' => $btTpModel,
                ]);
            }
        }

        $analysis = $this->stochasticService->analyzeStock((int) $stock->id, $params);
        if ($analysis === null) {
            throw new NotFoundHttpException("Data historis tidak mencukupi untuk menganalisis {$symbol}.");
        }

        return $this->render('detail', [
            'stock' => $stock,
            'analysis' => $analysis,
            'params' => $params,
            'timeframes' => StochasticService::getTimeframes(),
            'runBacktest' => $runBacktest,
            'backtestResult' => $backtestResult,
            'optimizeResult' => $optimizeResult,
        ]);
    }
}
