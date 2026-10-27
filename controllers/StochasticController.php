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

        // Jalankan scan
        $results = $this->stochasticService->scanAll($params);

        // Sorting
        $sort = $params['sort'];
        usort($results, function ($a, $b) use ($sort) {
            return match ($sort) {
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
            'shakeout_low_count' => 0,
            'golden_cross_oversold' => 0,
            'bullish_divergence' => 0,
            'total_buy' => 0,
            'total_sell' => 0,
            'oversold_count' => 0,
            'overbought_count' => 0,
        ];

        foreach ($results as $r) {
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

        return $this->render('index', [
            'results' => $results,
            'params' => $params,
            'stats' => $stats,
            'sectors' => $sectors,
            'timeframes' => StochasticService::getTimeframes(),
            'presets' => StochasticService::getStrategyPresets(),
        ]);
    }

    /**
     * Halaman Detail & Interactive Chart Stochastic Trading Plan.
     */
    public function actionDetail(string $symbol): string
    {
        $symbol = strtoupper(trim($symbol));
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

        $analysis = $this->stochasticService->analyzeStock((int) $stock->id, $params);
        if ($analysis === null) {
            throw new NotFoundHttpException("Data historis tidak mencukupi untuk menganalisis {$symbol}.");
        }

        return $this->render('detail', [
            'stock' => $stock,
            'analysis' => $analysis,
            'params' => $params,
            'timeframes' => StochasticService::getTimeframes(),
        ]);
    }
}
