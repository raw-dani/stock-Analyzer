<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\DoubleBottomService;
use Yii;
use yii\web\Controller;

/**
 * Double Bottom Pattern Scanner - deteksi pola double bottom pada berbagai timeframe.
 * Perbaikan: default terpusat (DoubleBottomService::DEFAULT_*), validasi semua
 * parameter, cache hasil scan 15 menit, filter breakoutOnly/minConfidence,
 * sorting server-side, statistik coverage dihitung di controller (sekali).
 */
final class DoubleBottomController extends Controller
{
    private const SCAN_CACHE_TTL = 900;
    private const SORTS = ['confidence', 'target', 'symbol', 'low1_date'];

    public function actionIndex(): string
    {
        $params = $this->resolveParams();
        Yii::info("Double Bottom Scan: timeframe={$params['timeframe']}, lookback={$params['lookback']}, tolerance={$params['tolerance']}", 'app\services\doublebottom');
        [$results, $fromCache] = $this->cachedScan($params);
        $results = $this->sortResults($results, $params['sort']);
        Yii::info('Double Bottom Scan: found ' . count($results) . ' patterns', 'app\services\doublebottom');
        return $this->render('index', array_merge($params, [
            'results' => $results,
            'fromCache' => $fromCache,
            'timeframes' => DoubleBottomService::getTimeframes(),
            'filterParams' => $this->filterParams($params),
            'stats' => $this->scanStats(),
            'lookbackDays' => $params['lookback'],
        ]));
    }

    public function actionDetail(string $symbol): string
    {
        $params = $this->resolveParams();
        $stock = \app\models\Stock::find()->where(['symbol' => $symbol])->one();
        if ($stock === null) {
            throw new \yii\web\NotFoundHttpException("Stock {$symbol} not found.");
        }
        $service = new DoubleBottomService();
        $pattern = null;
        $candles = [];
        try {
            $pattern = $service->detectPattern($stock->id, $params['timeframe'], $params['lookback'], $params['tolerance'], $params['minSeparation'], $params['maxSeparation'], $params['necklineMinDepth']);
            [$candles] = $service->getAnalysisCandles($stock->id, $params['timeframe'], $params['lookback']);
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('warning', $e->getMessage());
        }
        return $this->render('detail', array_merge($params, [
            'stock' => $stock,
            'pattern' => $pattern,
            'candles' => $candles,
            'timeframes' => DoubleBottomService::getTimeframes(),
            'filterParams' => $this->filterParams($params),
            'lookbackDays' => $params['lookback'],
        ]));
    }

    private function resolveParams(): array
    {
        $req = Yii::$app->request;
        $flashed = false;
        $flash = function (string $msg) use (&$flashed): void {
            if (!$flashed) { Yii::$app->session->setFlash('warning', $msg); $flashed = true; }
        };
        $timeframe = (int) $req->get('timeframe', DoubleBottomService::DEFAULT_TIMEFRAME);
        if (!isset(DoubleBottomService::getTimeframes()[$timeframe])) {
            $flash("Timeframe tidak valid - memakai default '" . DoubleBottomService::getTimeframeLabel(DoubleBottomService::DEFAULT_TIMEFRAME) . "'.");
            $timeframe = DoubleBottomService::DEFAULT_TIMEFRAME;
        }
        $lookback = (int) $req->get('lookback', DoubleBottomService::DEFAULT_LOOKBACK);
        if ($lookback < 5 || $lookback > 500) {
            $flash('Lookback di luar 5-500 candle - memakai default ' . DoubleBottomService::DEFAULT_LOOKBACK . '.');
            $lookback = DoubleBottomService::DEFAULT_LOOKBACK;
        }
        $tolerancePercent = (float) $req->get('tolerance', DoubleBottomService::DEFAULT_TOLERANCE * 100);
        if (!is_finite($tolerancePercent) || $tolerancePercent < 0.1 || $tolerancePercent > 20) {
            $flash('Tolerance di luar 0.1-20% - memakai default ' . (DoubleBottomService::DEFAULT_TOLERANCE * 100) . '%.');
            $tolerancePercent = DoubleBottomService::DEFAULT_TOLERANCE * 100;
        }
        $tolerance = $tolerancePercent / 100;
        $minSeparation = (int) $req->get('minSeparation', DoubleBottomService::DEFAULT_MIN_SEPARATION);
        if ($minSeparation < 2) {
            $flash('Separasi minimum minimal 2 candle - memakai default ' . DoubleBottomService::DEFAULT_MIN_SEPARATION . '.');
            $minSeparation = DoubleBottomService::DEFAULT_MIN_SEPARATION;
        }
        $maxSepRaw = $req->get('maxSeparation', '');
        $maxSeparation = ($maxSepRaw === '' || $maxSepRaw === null) ? (int) floor($lookback / 2) : (int) $maxSepRaw;
        if ($maxSeparation < $minSeparation) {
            $flash('Separasi maksimum < minimum - memakai otomatis (lookback/2).');
            $maxSeparation = (int) floor($lookback / 2);
        }
        $neckRaw = $req->get('necklineMinDepth', '');
        $necklineMinDepth = ($neckRaw === '' || $neckRaw === null) ? DoubleBottomService::NECKLINE_MIN_DEPTH : ((float) $neckRaw / 100);
        if (!is_finite($necklineMinDepth) || $necklineMinDepth < 0 || $necklineMinDepth > 0.5) {
            $flash('Neckline min depth di luar 0-50% - memakai default 1.5%.');
            $necklineMinDepth = DoubleBottomService::NECKLINE_MIN_DEPTH;
        }
        $minConfidence = (int) $req->get('minConfidence', 0);
        if ($minConfidence < 0 || $minConfidence > 100) {
            $flash('Confidence minimum di luar 0-100 - memakai 0.');
            $minConfidence = 0;
        }
        $breakoutOnly = (bool) $req->get('breakoutOnly', false);
        $sort = (string) $req->get('sort', 'confidence');
        if (!in_array($sort, self::SORTS, true)) { $sort = 'confidence'; }
        return compact('timeframe', 'lookback', 'tolerance', 'tolerancePercent', 'minSeparation', 'maxSeparation', 'necklineMinDepth', 'breakoutOnly', 'minConfidence', 'sort');
    }

    private function filterParams(array $p): array
    {
        return [
            'timeframe' => $p['timeframe'], 'lookback' => $p['lookback'],
            'tolerance' => round($p['tolerancePercent'], 1),
            'minSeparation' => $p['minSeparation'], 'maxSeparation' => $p['maxSeparation'],
            'necklineMinDepth' => round($p['necklineMinDepth'] * 100, 1),
            'breakoutOnly' => $p['breakoutOnly'] ? 1 : 0,
            'minConfidence' => $p['minConfidence'], 'sort' => $p['sort'],
        ];
    }

    private function cachedScan(array $p): array
    {
        $args = [$p['timeframe'], $p['lookback'], $p['tolerance'], $p['minSeparation'], $p['maxSeparation'], $p['breakoutOnly'], $p['minConfidence'], $p['necklineMinDepth']];
        $key = 'double-bottom:' . md5(json_encode($args));
        $cached = Yii::$app->cache->get($key);
        if (is_array($cached)) { return [$cached, true]; }
        $service = new DoubleBottomService();
        try {
            $results = $service->scanAll($p['timeframe'], $p['lookback'], $p['tolerance'], $p['minSeparation'], $p['maxSeparation'], $p['breakoutOnly'], $p['minConfidence'], $p['necklineMinDepth']);
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
            case 'target':
                usort($results, fn ($a, $b) => (($b['target_price'] - $b['current_price']) / max($b['current_price'], 0.0001)) <=> (($a['target_price'] - $a['current_price']) / max($a['current_price'], 0.0001)));
                break;
            case 'symbol':
                usort($results, fn ($a, $b) => strcmp($a['symbol'], $b['symbol']));
                break;
            case 'low1_date':
                usort($results, fn ($a, $b) => strcmp($b['low1_date'] ?? '', $a['low1_date'] ?? ''));
                break;
            default:
               
                usort($results, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        }
        return $results;
    }

    private function scanStats(): array
    {
        $key = 'double-bottom:stats';
        $cached = Yii::$app->cache->get($key);
        if (is_array($cached)) { return $cached; }
        $stats = [
            'stockCount' => (int) \app\models\Stock::find()->where(['active' => true])->count(),
            'priceCount' => (int) \app\models\DailyPrice::find()->count(),
            'intradayCount' => (int) \app\models\IntradayPrice::find()->count(),
            'dailyMax' => \app\models\DailyPrice::find()->max('date'),
            'intradayMax' => \app\models\IntradayPrice::find()->max('datetime'),
            'intradaySyms' => (int) \app\models\IntradayPrice::find()->select('stock_id')->distinct()->count(),
        ];
        Yii::$app->cache->set($key, $stats, self::SCAN_CACHE_TTL);
        return $stats;
    }
}
