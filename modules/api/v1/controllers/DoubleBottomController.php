<?php

declare(strict_types=1);

namespace app\modules\api\v1\controllers;

use app\models\Stock;
use app\services\DoubleBottomService;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * GET api/v1/double-bottom — scan pola Double Bottom berbasis harga.
 *
 * Query params (opsional, default dari DoubleBottomService::DEFAULT_*):
 *   timeframe       : 1|2|4|24 (1H/2H/4H/1D) default 4
 *   lookback        : int (5-500) default 60
 *   tolerance       : float (0.1-20, dalam %) default 2.0
 *   minSeparation   : int (>=2) default 3
 *   maxSeparation   : int (>=minSeparation) default lookback/2
 *   necklineMin     : float (0-50, dalam %) default 1.5
 *   symbol          : string (filter satu simbol)
 *   minConfidence   : int (0-100) filter confidence minimum
 *   breakoutOnly    : bool (true/false) hanya hasil breakout
 *   limit           : int (1-500) default 100
 */
final class DoubleBottomController extends BaseController
{
    public function actionIndex(): array
    {
        $defaults = Yii::$app->params['doubleBottomDefaults'] ?? [];

        $timeframe     = (int) Yii::$app->request->get('timeframe', DoubleBottomService::DEFAULT_TIMEFRAME);
        $lookback      = (int) Yii::$app->request->get('lookback', $defaults['lookback'] ?? DoubleBottomService::DEFAULT_LOOKBACK);
        $tolerancePct  = (float) Yii::$app->request->get('tolerance', $defaults['tolerance'] ?? DoubleBottomService::DEFAULT_TOLERANCE * 100);
        $minSeparation = (int) Yii::$app->request->get('minSeparation', $defaults['minSeparation'] ?? DoubleBottomService::DEFAULT_MIN_SEPARATION);
        $maxSepRaw     = Yii::$app->request->get('maxSeparation');
        $necklinePct   = (float) Yii::$app->request->get('necklineMin', $defaults['necklineMin'] ?? DoubleBottomService::NECKLINE_MIN_DEPTH * 100);
        $symbol        = Yii::$app->request->get('symbol');
        $minConfidence = (int) Yii::$app->request->get('minConfidence', 0);
        $breakoutOnly  = filter_var(Yii::$app->request->get('breakoutOnly', false), FILTER_VALIDATE_BOOLEAN);
        $limit         = min(500, max(1, (int) Yii::$app->request->get('limit', 100)));

        if (!isset(DoubleBottomService::getTimeframes()[$timeframe])) {
            throw new BadRequestHttpException('Invalid timeframe. Must be one of: ' . implode(', ', array_keys(DoubleBottomService::getTimeframes())));
        }

        $maxSeparation = ($maxSepRaw === null || $maxSepRaw === '') ? null : (int) $maxSepRaw;

        $logCat = $defaults['logCategory'] ?? 'app\\services\\doublebottom';
        Yii::info("Double Bottom API Scan: timeframe={$timeframe} lookback={$lookback} tolerance={$tolerancePct}% symbol=" . ($symbol ?? 'all'), $logCat);

        $service = new DoubleBottomService();

        try {
            $results = $service->scanAll($timeframe, $lookback, $tolerancePct / 100, $minSeparation, $maxSeparation, $breakoutOnly, $minConfidence, $necklinePct / 100);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($symbol !== null) {
            $results = array_filter($results, fn ($r) => strcasecmp((string) $r['symbol'], (string) $symbol) === 0);
        }

        $results = array_slice(array_values($results), 0, $limit);
        $formatted = array_map(fn (array $r) => $this->formatPattern($r), $results);

        return [
            'criteria' => [
                'timeframe'       => $timeframe,
                'timeframe_label' => DoubleBottomService::getTimeframeLabel($timeframe),
                'lookback'        => $lookback,
                'tolerance'       => $tolerancePct,
                'min_separation'  => $minSeparation,
                'max_separation'  => $maxSeparation,
                'neckline_min'    => $necklinePct,
                'symbol'          => $symbol,
                'min_confidence'  => $minConfidence,
                'breakout_only'   => $breakoutOnly,
            ],
            'items'    => $formatted,
            'total'    => count($formatted),
        ];
    }

    /**
     * GET api/v1/double-bottom/detail?symbol=SYMBOL
     * Detail pattern untuk satu simbol.
     */
    public function actionDetail(): array
    {
        $defaults = Yii::$app->params['doubleBottomDefaults'] ?? [];

        $symbol = Yii::$app->request->get('symbol');
        if (!$symbol) {
            throw new BadRequestHttpException('Parameter "symbol" wajib diisi.');
        }

        $timeframe     = (int) Yii::$app->request->get('timeframe', DoubleBottomService::DEFAULT_TIMEFRAME);
        $lookback      = (int) Yii::$app->request->get('lookback', $defaults['lookback'] ?? DoubleBottomService::DEFAULT_LOOKBACK);
        $tolerancePct  = (float) Yii::$app->request->get('tolerance', $defaults['tolerance'] ?? DoubleBottomService::DEFAULT_TOLERANCE * 100);
        $minSeparation = (int) Yii::$app->request->get('minSeparation', $defaults['minSeparation'] ?? DoubleBottomService::DEFAULT_MIN_SEPARATION);
        $maxSepRaw     = Yii::$app->request->get('maxSeparation');
        $necklinePct   = (float) Yii::$app->request->get('necklineMin', $defaults['necklineMin'] ?? DoubleBottomService::NECKLINE_MIN_DEPTH * 100);

        if (!isset(DoubleBottomService::getTimeframes()[$timeframe])) {
            throw new BadRequestHttpException('Invalid timeframe. Must be one of: ' . implode(', ', array_keys(DoubleBottomService::getTimeframes())));
        }

        $stock = Stock::find()->where(['symbol' => $symbol])->one();
        if ($stock === null) {
            return ['symbol' => $symbol, 'found' => false, 'message' => 'Stock not found'];
        }

        $service = new DoubleBottomService();

        try {
            $pattern = $service->detectPattern(
                (int) $stock->id,
                $timeframe,
                $lookback,
                $tolerancePct / 100,
                $minSeparation,
                ($maxSepRaw === null || $maxSepRaw === '') ? null : (int) $maxSepRaw,
                $necklinePct / 100
            );
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($pattern === null) {
            return [
                'symbol'  => $symbol,
                'found'   => false,
                'message' => 'No Double Bottom pattern detected with current settings.',
            ];
        }

        return [
            'symbol'  => $symbol,
            'name'    => $stock->name,
            'sector'  => $stock->sector ?? null,
            'found'   => true,
            'pattern' => $this->formatPattern($pattern + ['stock_name' => $stock->name, 'sector' => $stock->sector]),
        ];
    }

    /** Format satu hasil pattern agar konsisten antara index & detail. */
    private function formatPattern(array $r): array
    {
        $avgLow = ((float) $r['low1_price'] + (float) $r['low2_price']) / 2;
        $stopLoss = $r['stop_loss'] ?? $avgLow * (1 - 0.02);
        $current = (float) $r['current_price'];
        $target = (float) $r['target_price'];

        return [
            'symbol'        => $r['symbol'],
            'name'          => $r['stock_name'] ?? null,
            'sector'        => $r['sector'] ?? null,
            'timeframe'     => DoubleBottomService::getTimeframeLabel((int) $r['timeframe']),
            'data_source'   => $r['data_source'] ?? (($r['approximated'] ?? false) ? 'daily_fallback' : ((int) $r['timeframe'] === DoubleBottomService::TIMEFRAME_1D ? 'daily' : 'intraday')),
            'approximated'  => (bool) ($r['approximated'] ?? false),
            'low1'          => ['price' => round((float) $r['low1_price'], 2), 'date' => $r['low1_date'] ?? null],
            'low2'          => ['price' => round((float) $r['low2_price'], 2), 'date' => $r['low2_date'] ?? null],
            'neckline'      => ['price' => round((float) $r['neckline'], 2), 'date' => $r['neckline_date'] ?? null],
            'current_price' => round($current, 2),
            'breakout'      => (bool) ($r['breakout'] ?? false),
            'confidence'    => (int) ($r['confidence'] ?? 0),
            'volume_ratio'  => isset($r['volume_ratio']) ? round((float) $r['volume_ratio'], 2) : null,
            'target_price'  => round($target, 2),
            'risk_reward'   => isset($r['risk_reward']) && $r['risk_reward'] !== null ? round((float) $r['risk_reward'], 2) : null,
            'trading_plan'  => [
                'entry'                => round($current, 2),
                'stop_loss'            => round((float) $stopLoss, 2),
                'target'               => round($target, 2),
                'potential_upside_pct' => $current > 0 ? round(($target - $current) / $current * 100, 1) : null,
                'max_risk_pct'         => $current > 0 ? round(($current - (float) $stopLoss) / $current * 100, 1) : null,
            ],
        ];
    }
}
