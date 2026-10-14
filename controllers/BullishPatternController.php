<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use app\services\BullishPatternService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controller untuk Scanner & Detail Pola Bullish Continuation & Breakout:
 * Cup and Handle, Bullish Rectangle, Bullish Pennant, Bullish Flag, dan Ascending Triangle.
 */
final class BullishPatternController extends Controller
{
    private const SCAN_CACHE_TTL = 900;
    private const SORTS = ['confidence', 'target', 'rr', 'distance', 'rvol', 'symbol'];

    public function actionIndex(): string
    {
        $params = $this->resolveParams();
        Yii::info("Bullish Patterns Scan: timeframe={$params['timeframe']}, patternType={$params['patternType']}", 'app\controllers\bullishpattern');
        [$results, $fromCache] = $this->cachedScan($params);
        $results = $this->sortResults($results, $params['sort']);

        $cupCount = count(array_filter($results, fn($r) => ($r['pattern_type'] ?? '') === BullishPatternService::PATTERN_CUP_AND_HANDLE));
        $rectCount = count(array_filter($results, fn($r) => ($r['pattern_type'] ?? '') === BullishPatternService::PATTERN_BULLISH_RECTANGLE));
        $pennantCount = count(array_filter($results, fn($r) => ($r['pattern_type'] ?? '') === BullishPatternService::PATTERN_BULLISH_PENNANT));
        $flagCount = count(array_filter($results, fn($r) => ($r['pattern_type'] ?? '') === BullishPatternService::PATTERN_BULLISH_FLAG));
        $triangleCount = count(array_filter($results, fn($r) => ($r['pattern_type'] ?? '') === BullishPatternService::PATTERN_ASCENDING_TRIANGLE));

        $breakoutCount = count(array_filter($results, fn($r) => !empty($r['breakout'])));
        $buyZoneCount = count(array_filter($results, fn($r) => in_array($r['trade_status'] ?? '', [
            BullishPatternService::STATUS_BUY_ZONE,
            BullishPatternService::STATUS_RETEST,
            BullishPatternService::STATUS_CONSOLIDATION_BOUNCE
        ], true)));
        $bullTrapCount = count(array_filter($results, fn($r) => !empty($r['bull_trap_detected'])));
        $highRrCount = count(array_filter($results, fn($r) => ((float)($r['best_rr'] ?? 0)) >= 2.0));

        return $this->render('index', array_merge($params, [
            'results' => $results,
            'fromCache' => $fromCache,
            'timeframes' => BullishPatternService::getTimeframes(),
            'patternTypes' => BullishPatternService::getPatternTypes(),
            'statusOptions' => BullishPatternService::getStatusOptions(),
            'falseBreakoutFilters' => BullishPatternService::getFalseBreakoutFilters(),
            'filterParams' => $this->filterParams($params),
            'stats' => $this->scanStats(),
            'lookbackDays' => $params['lookback'],
            'cupCount' => $cupCount,
            'rectCount' => $rectCount,
            'pennantCount' => $pennantCount,
            'flagCount' => $flagCount,
            'triangleCount' => $triangleCount,
            'breakoutCount' => $breakoutCount,
            'buyZoneCount' => $buyZoneCount,
            'bullTrapCount' => $bullTrapCount,
            'highRrCount' => $highRrCount,
        ]));
    }

    public function actionDetail(string $symbol): string
    {
        $params = $this->resolveParams();
        $stock = Stock::find()->where(['symbol' => $symbol])->one();
        if ($stock === null) {
            throw new NotFoundHttpException("Stock {$symbol} tidak ditemukan.");
        }

        $service = new BullishPatternService();
        $pattern = null;
        $candles = [];
        try {
            $pattern = $service->detectPattern(
                $stock->id,
                $params['timeframe'],
                $params['lookback'],
                $params['patternType']
            );
            [$candles] = $service->getAnalysisCandles($stock->id, $params['timeframe'], $params['lookback']);
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('warning', $e->getMessage());
        }

        return $this->render('detail', array_merge($params, [
            'stock' => $stock,
            'pattern' => $pattern,
            'candles' => $candles,
            'timeframes' => BullishPatternService::getTimeframes(),
            'patternTypes' => BullishPatternService::getPatternTypes(),
            'statusOptions' => BullishPatternService::getStatusOptions(),
            'falseBreakoutFilters' => BullishPatternService::getFalseBreakoutFilters(),
            'filterParams' => $this->filterParams($params),
            'stats' => $this->scanStats(),
            'lookbackDays' => $params['lookback'],
        ]));
    }

    private function resolveParams(): array
    {
        $req = Yii::$app->request;
        $tf = (int) $req->get('timeframe', BullishPatternService::DEFAULT_TIMEFRAME);
        $lookback = (int) $req->get('lookback', $req->get('lookbackDays', BullishPatternService::DEFAULT_LOOKBACK));
        $patternType = (string) $req->get('patternType', BullishPatternService::PATTERN_ALL);
        $statusFilter = (string) $req->get('statusFilter', 'all');
        $minRr = (float) $req->get('minRr', 0.0);
        $breakoutOnly = (bool) $req->get('breakoutOnly', false);
        $volOnly = (bool) $req->get('volOnly', false);
        $falseBreakoutFilter = (string) $req->get('falseBreakoutFilter', BullishPatternService::RISK_ALL);
        $minConf = (int) $req->get('minConfidence', 0);
        $sort = (string) $req->get('sort', 'confidence');
        if (!in_array($sort, self::SORTS, true)) {
            $sort = 'confidence';
        }

        return [
            'timeframe' => $tf,
            'lookback' => max(15, min(500, $lookback)),
            'patternType' => $patternType,
            'statusFilter' => $statusFilter,
            'minRr' => max(0.0, $minRr),
            'breakoutOnly' => $breakoutOnly,
            'volOnly' => $volOnly,
            'falseBreakoutFilter' => $falseBreakoutFilter,
            'minConfidence' => max(0, min(100, $minConf)),
            'sort' => $sort,
        ];
    }

    private function filterParams(array $p): array
    {
        return [
            'timeframe' => $p['timeframe'],
            'lookback' => $p['lookback'],
            'patternType' => $p['patternType'] !== BullishPatternService::PATTERN_ALL ? $p['patternType'] : null,
            'statusFilter' => $p['statusFilter'] !== 'all' ? $p['statusFilter'] : null,
            'minRr' => $p['minRr'] > 0 ? $p['minRr'] : null,
            'breakoutOnly' => $p['breakoutOnly'] ? '1' : null,
            'volOnly' => $p['volOnly'] ? '1' : null,
            'falseBreakoutFilter' => $p['falseBreakoutFilter'] !== BullishPatternService::RISK_ALL ? $p['falseBreakoutFilter'] : null,
            'minConfidence' => $p['minConfidence'] ?: null,
        ];
    }

    private function cachedScan(array $p): array
    {
        $args = [
            $p['timeframe'],
            $p['lookback'],
            $p['patternType'],
            $p['statusFilter'],
            $p['minRr'],
            $p['breakoutOnly'],
            $p['volOnly'],
            $p['falseBreakoutFilter'],
            $p['minConfidence']
        ];
        $key = 'bullish-pattern:v1:' . md5(json_encode($args));
        $cached = Yii::$app->cache->get($key);
        if (is_array($cached)) {
            return [$cached, true];
        }

        $service = new BullishPatternService();
        try {
            $results = $service->scanAll(
                $p['timeframe'],
                $p['lookback'],
                $p['patternType'],
                $p['statusFilter'],
                $p['minRr'],
                $p['breakoutOnly'],
                $p['volOnly'],
                $p['falseBreakoutFilter'],
                $p['minConfidence']
            );
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('warning', $e->getMessage());
            $results = [];
        }

        Yii::$app->cache->set($key, $results, self::SCAN_CACHE_TTL);
        return [$results, false];
    }

    private function sortResults(array $results, string $sort): array
    {
        switch ($sort) {
            case 'rr':
                usort($results, fn ($a, $b) => ((float) ($b['best_rr'] ?? 0)) <=> ((float) ($a['best_rr'] ?? 0)));
                break;
            case 'distance':
                usort($results, fn ($a, $b) => abs((float) ($a['distance_resistance_pct'] ?? 999)) <=> abs((float) ($b['distance_resistance_pct'] ?? 999)));
                break;
            case 'rvol':
                usort($results, fn ($a, $b) => ((float) ($b['rvol'] ?? 0)) <=> ((float) ($a['rvol'] ?? 0)));
                break;
            case 'target':
                usort($results, fn ($a, $b) => (($b['target_price'] - $b['current_price']) / max($b['current_price'], 0.0001)) <=> (($a['target_price'] - $a['current_price']) / max($a['current_price'], 0.0001)));
                break;
            case 'symbol':
                usort($results, fn ($a, $b) => strcmp($a['symbol'], $b['symbol']));
                break;
            default:
                usort($results, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        }
        return $results;
    }

    private function scanStats(): array
    {
        $key = 'bullish-pattern:stats';
        $cached = Yii::$app->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $stats = [
            'stockCount' => (int) Stock::find()->where(['active' => true])->count(),
            'priceCount' => (int) DailyPrice::find()->count(),
            'intradayCount' => (int) IntradayPrice::find()->count(),
            'dailyMax' => DailyPrice::find()->max('date'),
            'intradayMax' => IntradayPrice::find()->max('datetime'),
        ];
        Yii::$app->cache->set($key, $stats, self::SCAN_CACHE_TTL);
        return $stats;
    }
}
