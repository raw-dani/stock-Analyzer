<?php

declare(strict_types=1);

namespace app\modules\api\v1\controllers;

use app\models\Stock;
use app\services\RsiDoubleBottomService;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * GET api/v1/rsi-double-bottom — scan RSI Double Bottom patterns.
 *
 * Query params (opsional, default dari params.rsiDefaults):
 *   timeframe    : 1|2|4|24 (1H/2H/4H/1D) default 4
 *   lookback     : int (20-300) default 150
 *   tolerance    : float (0.5-10) default 3.0
 *   rsiPeriod    : int (2-50) default 14
 *   maxRsi       : float (0-100) default 40.0
 *   minSeparation: int (>=1) default 5
 *   maxSeparation: int (>=minSeparation) default 30
 *   necklineMin  : float (>=0) default 2.0
 *   symbol       : string (filter satu simbol)
 *   minConfidence: int (0-100) filter confidence minimum
 *   breakoutOnly : bool (true/false) hanya hasil breakout
 *   limit        : int (1-500) default 100
 */
final class RsiDoubleBottomController extends BaseController
{
    public function actionIndex(): array
    {
        $defaults = Yii::$app->params['rsiDefaults'] ?? [];

        $timeframe       = (int) (Yii::$app->request->get('timeframe', self::TIMEFRAME_4H));
        $lookback        = (int) (Yii::$app->request->get('lookback', $defaults['lookback'] ?? 150));
        $tolerance       = (float) (Yii::$app->request->get('tolerance', $defaults['tolerance'] ?? 3.0));
        $rsiPeriod       = (int) (Yii::$app->request->get('rsiPeriod', $defaults['rsiPeriod'] ?? 14));
        $maxRsi          = (float) (Yii::$app->request->get('maxRsi', $defaults['maxRsiForBottom'] ?? 40.0));
        $minSeparation   = (int) (Yii::$app->request->get('minSeparation', $defaults['minSeparation'] ?? 5));
        $maxSeparation   = (int) (Yii::$app->request->get('maxSeparation', $defaults['maxSeparation'] ?? 30));
        $necklineMin     = (float) (Yii::$app->request->get('necklineMin', $defaults['necklineMin'] ?? 2.0));
        $symbol          = Yii::$app->request->get('symbol');
        $minConfidence   = (int) (Yii::$app->request->get('minConfidence', 0));
        $breakoutOnly    = filter_var(Yii::$app->request->get('breakoutOnly', false), FILTER_VALIDATE_BOOLEAN);
        $limit           = min(500, max(1, (int) Yii::$app->request->get('limit', 100)));

        $timeframes = self::getTimeframes();
        if (!isset($timeframes[$timeframe])) {
            throw new BadRequestHttpException('Invalid timeframe. Must be one of: ' . implode(', ', array_keys($timeframes)));
        }

        $logCat = $defaults['logCategory'] ?? 'app\services\rsidoublebottom';
        Yii::info("RSI Double Bottom API Scan: timeframe={$timeframe} lookback={$lookback} tolerance={$tolerance} period={$rsiPeriod} maxRsi={$maxRsi} symbol=" . ($symbol ?? 'all'), $logCat);

        $service = new RsiDoubleBottomService();

        try {
            $results = $service->scanAll(
                $timeframe,
                $lookback,
                $tolerance,
                $rsiPeriod,
                $maxRsi,
                $minSeparation,
                $maxSeparation,
                $necklineMin
            );
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        // Filter by symbol if provided
        if ($symbol !== null) {
            $results = array_filter($results, fn($r) => strcasecmp($r['symbol'], $symbol) === 0);
        }

        // Filter by min confidence
        if ($minConfidence > 0) {
            $results = array_filter($results, fn($r) => ($r['confidence'] ?? 0) >= $minConfidence);
        }

        // Filter breakout only
        if ($breakoutOnly) {
            $results = array_filter($results, fn($r) => !empty($r['breakout']));
        }

        // Limit results
        $results = array_slice(array_values($results), 0, $limit);

        $formatted = array_map(function (array $r) {
            return [
                'symbol'            => $r['symbol'],
                'name'              => $r['stock_name'],
                'sector'            => $r['sector'] ?? null,
                'timeframe'         => RsiDoubleBottomService::getTimeframeLabel($r['timeframe']),
                'data_source'       => $r['data_source'] ?? ($r['approximated'] ?? false ? 'daily_fallback' : ($r['timeframe'] === 24 ? 'daily' : 'intraday')),
                'approximated'      => (bool) ($r['approximated'] ?? false),
                'rsi_period'        => $r['rsi_period'] ?? 14,
                'rsi1'              => [
                    'value' => round((float) ($r['rsi1_value'] ?? 0), 2),
                    'date'  => $r['rsi1_date'] ?? null,
                    'price' => round((float) ($r['low1_price'] ?? 0), 2),
                ],
                'rsi2'              => [
                    'value' => round((float) ($r['rsi2_value'] ?? 0), 2),
                    'date'  => $r['rsi2_date'] ?? null,
                    'price' => round((float) ($r['low2_price'] ?? 0), 2),
                ],
                'neckline'          => [
                    'rsi'   => round((float) ($r['neckline_rsi'] ?? 0), 2),
                    'date'  => $r['neckline_date'] ?? null,
                    'price' => round((float) ($r['neckline_price'] ?? 0), 2),
                ],
                'current'           => [
                    'rsi'   => round((float) ($r['current_rsi'] ?? 0), 2),
                    'price' => round((float) ($r['current_price'] ?? 0), 2),
                ],
                'breakout'          => (bool) ($r['breakout'] ?? false),
                'divergence'        => (bool) ($r['divergence'] ?? false),
                'confidence'        => (int) ($r['confidence'] ?? 0),
                'target_price'      => round((float) ($r['target_price'] ?? 0), 2),
                'risk_reward'       => $r['risk_reward'] !== null ? round((float) $r['risk_reward'], 2) : null,
            ];
        }, $results);

        return [
            'criteria' => [
                'timeframe'       => $timeframe,
                'lookback'        => $lookback,
                'tolerance'       => $tolerance,
                'rsi_period'      => $rsiPeriod,
                'max_rsi'         => $maxRsi,
                'min_separation'  => $minSeparation,
                'max_separation'  => $maxSeparation,
                'neckline_min'    => $necklineMin,
                'symbol'          => $symbol,
                'min_confidence'  => $minConfidence,
                'breakout_only'   => $breakoutOnly,
            ],
            'items'    => $formatted,
            'total'    => count($formatted),
        ];
    }

    /**
     * GET api/v1/rsi-double-bottom/detail?symbol=SYMBOL
     * Detail pattern untuk satu simbol.
     */
    public function actionDetail(): array
    {
        $defaults = Yii::$app->params['rsiDefaults'] ?? [];

        $symbol = Yii::$app->request->get('symbol');
        if (!$symbol) {
            throw new BadRequestHttpException('Parameter "symbol" wajib diisi.');
        }

        $timeframe       = (int) (Yii::$app->request->get('timeframe', self::TIMEFRAME_4H));
        $lookback        = (int) (Yii::$app->request->get('lookback', $defaults['lookback'] ?? 150));
        $tolerance       = (float) (Yii::$app->request->get('tolerance', $defaults['tolerance'] ?? 3.0));
        $rsiPeriod       = (int) (Yii::$app->request->get('rsiPeriod', $defaults['rsiPeriod'] ?? 14));
        $maxRsi          = (float) (Yii::$app->request->get('maxRsi', $defaults['maxRsiForBottom'] ?? 40.0));
        $minSeparation   = (int) (Yii::$app->request->get('minSeparation', $defaults['minSeparation'] ?? 5));
        $maxSeparation   = (int) (Yii::$app->request->get('maxSeparation', $defaults['maxSeparation'] ?? 30));
        $necklineMin     = (float) (Yii::$app->request->get('necklineMin', $defaults['necklineMin'] ?? 2.0));

        $stock = Stock::find()->where(['symbol' => $symbol])->one();
        if ($stock === null) {
            return [
                'symbol' => $symbol,
                'found'  => false,
                'message' => 'Stock not found',
            ];
        }

        $service = new RsiDoubleBottomService();

        try {
            $pattern = $service->detectPattern(
                $stock->id,
                $timeframe,
                $lookback,
                $tolerance,
                $rsiPeriod,
                $maxRsi,
                $minSeparation,
                $maxSeparation,
                $necklineMin
            );
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($pattern === null) {
            return [
                'symbol'  => $symbol,
                'found'   => false,
                'message' => 'No RSI Double Bottom pattern detected with current settings.',
            ];
        }

        return [
            'symbol'   => $symbol,
            'name'     => $stock->name,
            'sector'   => $stock->sector ?? null,
            'found'    => true,
            'pattern'  => [
                'timeframe'         => RsiDoubleBottomService::getTimeframeLabel($pattern['timeframe']),
                'data_source'       => $pattern['data_source'] ?? ($pattern['approximated'] ?? false ? 'daily_fallback' : ($pattern['timeframe'] === 24 ? 'daily' : 'intraday')),
                'approximated'      => (bool) ($pattern['approximated'] ?? false),
                'rsi_period'        => $pattern['rsi_period'] ?? 14,
                'rsi1'              => [
                    'value' => round((float) ($pattern['rsi1_value'] ?? 0), 2),
                    'date'  => $pattern['rsi1_date'] ?? null,
                    'price' => round((float) ($pattern['low1_price'] ?? 0), 2),
                ],
                'rsi2'              => [
                    'value' => round((float) ($pattern['rsi2_value'] ?? 0), 2),
                    'date'  => $pattern['rsi2_date'] ?? null,
                    'price' => round((float) ($pattern['low2_price'] ?? 0), 2),
                ],
                'neckline'          => [
                    'rsi'   => round((float) ($pattern['neckline_rsi'] ?? 0), 2),
                    'date'  => $pattern['neckline_date'] ?? null,
                    'price' => round((float) ($pattern['neckline_price'] ?? 0), 2),
                ],
                'current'           => [
                    'rsi'   => round((float) ($pattern['current_rsi'] ?? 0), 2),
                    'price' => round((float) ($pattern['current_price'] ?? 0), 2),
                ],
                'breakout'          => (bool) ($pattern['breakout'] ?? false),
                'divergence'        => (bool) ($pattern['divergence'] ?? false),
                'confidence'        => (int) ($pattern['confidence'] ?? 0),
                'target_price'      => round((float) ($pattern['target_price'] ?? 0), 2),
                'risk_reward'       => $pattern['risk_reward'] !== null ? round((float) $pattern['risk_reward'], 2) : null,
                'trading_plan'      => [
                    'entry'         => round((float) ($pattern['current_price'] ?? 0), 2),
                    'stop_loss'     => round((float) (($pattern['low1_price'] + $pattern['low2_price']) / 2 * 0.98), 2),
                    'potential_upside_pct' => $pattern['current_price'] > 0
                        ? round((($pattern['target_price'] - $pattern['current_price']) / $pattern['current_price']) * 100, 1)
                        : null,
                    'max_risk_pct'  => $pattern['current_price'] > 0
                        ? round((($pattern['current_price'] - (($pattern['low1_price'] + $pattern['low2_price']) / 2 * 0.98)) / $pattern['current_price']) * 100, 1)
                        : null,
                ],
            ],
        ];
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