<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\RsiDoubleBottomService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Scanner RSI Strategy & Momentum Signal — TF 1D, 4H, 2H, 1H.
 * Memberikan rekomendasi Waktu Beli (Entry) dan Waktu Jual (Exit/Take Profit) yang tepat.
 */
final class RsiDoubleBottomController extends Controller
{
    /**
     * Halaman index — tampilkan hasil scan RSI Strategy.
     */
    public function actionIndex(
        ?int $timeframe = null,
        ?int $lookback = null,
        ?float $tolerance = null,
        ?int $rsiPeriod = null,
        ?float $maxRsi = null,
        ?string $statusFilter = 'all',
        ?int $minConf = 0,
        ?string $sortBy = 'confidence'
    ): string {
        $defaults = Yii::$app->params['rsiDefaults'] ?? [];
        $timeframe       = $timeframe       ?? self::TIMEFRAME_1D;
        $lookback        = $lookback        ?? ($defaults['lookback']        ?? 150);
        $tolerance       = $tolerance       ?? ($defaults['tolerance']       ?? 3.0);
        $rsiPeriod       = $rsiPeriod       ?? ($defaults['rsiPeriod']       ?? 14);
        $maxRsi          = $maxRsi          ?? ($defaults['maxRsiForBottom'] ?? 40.0);
        $minSeparation   = $defaults['minSeparation'] ?? 5;
        $maxSeparation   = $defaults['maxSeparation'] ?? 30;
        $necklineMin     = $defaults['necklineMin'] ?? 2.0;

        $timeframes = self::getTimeframes();
        if (!isset($timeframes[$timeframe])) {
            $timeframe = self::TIMEFRAME_1D;
        }

        $logCat = $defaults['logCategory'] ?? 'app\services\rsidoublebottom';
        Yii::info("RSI Strategy Scan: timeframe={$timeframe} lookback={$lookback} tolerance={$tolerance} period={$rsiPeriod} maxRsi={$maxRsi}", $logCat);

        $service = new RsiDoubleBottomService();
        try {
            $results = $service->scanAll($timeframe, $lookback, $tolerance, $rsiPeriod, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        } catch (\InvalidArgumentException $e) {
            Yii::warning("RSI Strategy Scan: {$e->getMessage()}", $logCat);
            $lookback = $defaults['lookback'] ?? 150;
            $tolerance = $defaults['tolerance'] ?? 3.0;
            $rsiPeriod = $defaults['rsiPeriod'] ?? 14;
            $maxRsi = $defaults['maxRsiForBottom'] ?? 40.0;
            $minSeparation = $defaults['minSeparation'] ?? 5;
            $maxSeparation = $defaults['maxSeparation'] ?? 30;
            $necklineMin = $defaults['necklineMin'] ?? 2.0;
            $results = $service->scanAll($timeframe, $lookback, $tolerance, $rsiPeriod, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        }

        $allResults = $results;

        // Enhanced Status / Signal Filter
        if ($statusFilter === 'buy') {
            $results = array_filter($results, fn ($r) => in_array($r['action'] ?? '', ['BUY_NOW', 'BUY_PULLBACK', 'MOMENTUM_BUY'], true));
        } elseif ($statusFilter === 'sell') {
            $results = array_filter($results, fn ($r) => in_array($r['action'] ?? '', ['SELL_NOW', 'TAKE_PROFIT'], true));
        } elseif ($statusFilter === 'bull_div') {
            $results = array_filter($results, fn ($r) => !empty($r['divergence']));
        } elseif ($statusFilter === 'bear_div') {
            $results = array_filter($results, fn ($r) => !empty($r['bearish_divergence']));
        } elseif ($statusFilter === 'oversold') {
            $results = array_filter($results, fn ($r) => ($r['current_rsi'] ?? 50) <= 35);
        } elseif ($statusFilter === 'overbought') {
            $results = array_filter($results, fn ($r) => ($r['current_rsi'] ?? 50) >= 65);
        } elseif ($statusFilter === 'breakout') {
            $results = array_filter($results, fn ($r) => !empty($r['breakout']));
        } elseif ($statusFilter === 'high_conf') {
            $results = array_filter($results, fn ($r) => ($r['confidence'] ?? 0) >= 75);
        }

        if ($minConf > 0) {
            $results = array_filter($results, fn ($r) => ($r['confidence'] ?? 0) >= $minConf);
        }

        // Sorting
        if ($sortBy === 'rr') {
            usort($results, fn ($a, $b) => ($b['risk_reward'] ?? 0) <=> ($a['risk_reward'] ?? 0));
        } elseif ($sortBy === 'upside') {
            usort($results, fn ($a, $b) => ($b['potential_upside'] ?? 0) <=> ($a['potential_upside'] ?? 0));
        } elseif ($sortBy === 'current_rsi') {
            usort($results, fn ($a, $b) => ($a['current_rsi'] ?? 0) <=> ($b['current_rsi'] ?? 0));
        } elseif ($sortBy === 'rsi_high') {
            usort($results, fn ($a, $b) => ($b['current_rsi'] ?? 0) <=> ($a['current_rsi'] ?? 0));
        } else {
            usort($results, fn ($a, $b) => ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0));
        }

        Yii::info("RSI Strategy Scan: found " . count($results) . " items", $logCat);

        return $this->render('index', [
            'results'        => $results,
            'allResults'     => $allResults,
            'timeframe'      => $timeframe,
            'lookback'       => $lookback,
            'tolerance'      => $tolerance,
            'rsiPeriod'      => $rsiPeriod,
            'maxRsi'         => $maxRsi,
            'statusFilter'   => $statusFilter,
            'minConf'        => $minConf,
            'sortBy'         => $sortBy,
            'minSeparation'  => $minSeparation,
            'maxSeparation'  => $maxSeparation,
            'necklineMin'    => $necklineMin,
            'timeframes'     => $timeframes,
        ]);
    }

    /**
     * Halaman detail satu simbol — analisis teknikal RSI, divergensi, dan level trading.
     */
    public function actionDetail(string $symbol): string
    {
        $defaults = Yii::$app->params['rsiDefaults'] ?? [];
        $timeframe = (int) Yii::$app->request->get('timeframe', self::TIMEFRAME_1D);
        $lookback  = (int) Yii::$app->request->get('lookback',  $defaults['lookback']   ?? 150);
        $tolerance = (float) Yii::$app->request->get('tolerance', $defaults['tolerance'] ?? 3.0);
        $rsiPeriod = (int) Yii::$app->request->get('rsiPeriod', $defaults['rsiPeriod']  ?? 14);
        $maxRsi    = (float) Yii::$app->request->get('maxRsi',    $defaults['maxRsiForBottom'] ?? 40.0);
        $statusFilter = (string) Yii::$app->request->get('statusFilter', 'all');
        $sortBy       = (string) Yii::$app->request->get('sortBy', 'confidence');
        $minSeparation = $defaults['minSeparation'] ?? 5;
        $maxSeparation = $defaults['maxSeparation'] ?? 30;
        $necklineMin = $defaults['necklineMin'] ?? 2.0;

        $timeframes = self::getTimeframes();
        if (!isset($timeframes[$timeframe])) {
            $timeframe = self::TIMEFRAME_1D;
        }

        $stock = \app\models\Stock::find()->where(['symbol' => $symbol])->one();
        if ($stock === null) {
            throw new NotFoundHttpException("Stock {$symbol} not found.");
        }

        $service = new RsiDoubleBottomService();
        try {
            $pattern = $service->detectPattern($stock->id, $timeframe, $lookback, $tolerance, $rsiPeriod, $maxRsi, $minSeparation, $maxSeparation, $necklineMin, true);
        } catch (\InvalidArgumentException $e) {
            Yii::warning("RSI detail {$symbol}: {$e->getMessage()}", $defaults['logCategory'] ?? 'app\services\rsidoublebottom');
            $timeframe = self::TIMEFRAME_1D;
            $lookback = $defaults['lookback'] ?? 150;
            $tolerance = $defaults['tolerance'] ?? 3.0;
            $rsiPeriod = $defaults['rsiPeriod'] ?? 14;
            $maxRsi = $defaults['maxRsiForBottom'] ?? 40.0;
            $minSeparation = $defaults['minSeparation'] ?? 5;
            $maxSeparation = $defaults['maxSeparation'] ?? 30;
            $necklineMin = $defaults['necklineMin'] ?? 2.0;
            $pattern = $service->detectPattern($stock->id, $timeframe, $lookback, $tolerance, $rsiPeriod, $maxRsi, $minSeparation, $maxSeparation, $necklineMin, true);
        }

        return $this->render('detail', [
            'stock'        => $stock,
            'pattern'      => $pattern,
            'timeframe'    => $timeframe,
            'lookback'     => $lookback,
            'tolerance'    => $tolerance,
            'rsiPeriod'    => $rsiPeriod,
            'maxRsi'       => $maxRsi,
            'statusFilter' => $statusFilter,
            'sortBy'       => $sortBy,
            'minSeparation'=> $minSeparation,
            'maxSeparation'=> $maxSeparation,
            'necklineMin'  => $necklineMin,
            'timeframes'   => $timeframes,
        ]);
    }

    public const TIMEFRAME_1H = RsiDoubleBottomService::TIMEFRAME_1H;
    public const TIMEFRAME_2H = RsiDoubleBottomService::TIMEFRAME_2H;
    public const TIMEFRAME_4H = RsiDoubleBottomService::TIMEFRAME_4H;
    public const TIMEFRAME_1D = RsiDoubleBottomService::TIMEFRAME_1D;

    public static function getTimeframes(): array
    {
        return RsiDoubleBottomService::getTimeframes();
    }
}
