<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\TripleBottomService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controller untuk Scanner & Detail Pola Triple Bottom
 * Meliputi deteksi 3 bottom, timing beli/jual, multi-target TP, dan stop loss.
 */
final class TripleBottomController extends Controller
{
    private const SCAN_CACHE_TTL = 900;
    private const SORTS = ['confidence', 'target', 'rr', 'distance', 'rvol', 'symbol', 'low1_date'];

    public function actionIndex(): string
    {
        $params = $this->resolveParams();
        Yii::info("Triple Bottom Scan: timeframe={$params['timeframe']}, lookback={$params['lookback']}", 'app\controllers\triplebottom');
        [$results, $fromCache] = $this->cachedScan($params);
        $results = $this->sortResults($results, $params['sort']);
        
        return $this->render('index', array_merge($params, [
            'results' => $results,
            'fromCache' => $fromCache,
            'timeframes' => TripleBottomService::getTimeframes(),
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
            throw new NotFoundHttpException("Stock {$symbol} tidak ditemukan.");
        }
        $service = new TripleBottomService();
        $pattern = null;
        $candles = [];
        try {
            $pattern = $service->detectPattern(
                $stock->id,
                $params['timeframe'],
                $params['lookback'],
                $params['tolerance'],
                $params['minSeparation'],
                $params['maxSeparation'],
                $params['necklineMinDepth']
            );
            [$candles] = $service->getAnalysisCandles($stock->id, $params['timeframe'], $params['lookback']);
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('warning', $e->getMessage());
        }

        return $this->render('detail', array_merge($params, [
            'stock' => $stock,
            'pattern' => $pattern,
            'candles' => $candles,
            'timeframes' => TripleBottomService::getTimeframes(),
            'filterParams' => $this->filterParams($params),
            'stats' => $this->scanStats(),
            'lookbackDays' => $params['lookback'],
        ]));
    }

    private function resolveParams(): array
    {
        $req = Yii::$app->request;
        $tf = (int) $req->get('timeframe', TripleBottomService::DEFAULT_TIMEFRAME);
        $lookback = (int) $req->get('lookback', $req->get('lookbackDays', TripleBottomService::DEFAULT_LOOKBACK));
        $tol = $req->get('tolerance');
        $tol = $tol !== null ? ((float) $tol > 1.0 ? (float) $tol / 100 : (float) $tol) : TripleBottomService::DEFAULT_TOLERANCE;
        $minSep = (int) $req->get('minSeparation', TripleBottomService::DEFAULT_MIN_SEPARATION);
        $maxSep = $req->get('maxSeparation') !== null ? (int) $req->get('maxSeparation') : null;
        $nmd = $req->get('necklineMinDepth');
        $nmd = $nmd !== null ? ((float) $nmd > 1.0 ? (float) $nmd / 100 : (float) $nmd) : TripleBottomService::NECKLINE_MIN_DEPTH;
        $breakoutOnly = (bool) $req->get('breakoutOnly', false);
        $minConf = (int) $req->get('minConfidence', 0);
        $statusFilter = (string) $req->get('statusFilter', 'all');
        $minRr = (float) $req->get('minRr', 0.0);
        $volOnly = (bool) $req->get('volOnly', false);
        $sort = (string) $req->get('sort', 'confidence');
        if (!in_array($sort, self::SORTS, true)) {
            $sort = 'confidence';
        }

        return [
            'timeframe' => $tf,
            'lookback' => max(15, min(500, $lookback)),
            'tolerance' => max(0.001, min(0.20, $tol)),
            'tolerancePercent' => round(max(0.001, min(0.20, $tol)) * 100, 1),
            'minSeparation' => max(2, $minSep),
            'maxSeparation' => $maxSep,
            'necklineMinDepth' => max(0.0, min(0.50, $nmd)),
            'breakoutOnly' => $breakoutOnly,
            'minConfidence' => max(0, min(100, $minConf)),
            'statusFilter' => $statusFilter,
            'minRr' => max(0.0, $minRr),
            'volOnly' => $volOnly,
            'sort' => $sort,
        ];
    }

    private function filterParams(array $p): array
    {
        return [
            'timeframe' => $p['timeframe'],
            'lookback' => $p['lookback'],
            'tolerance' => $p['tolerancePercent'],
            'minSeparation' => $p['minSeparation'],
            'maxSeparation' => $p['maxSeparation'],
            'necklineMinDepth' => round($p['necklineMinDepth'] * 100, 1),
            'breakoutOnly' => $p['breakoutOnly'] ? '1' : null,
            'minConfidence' => $p['minConfidence'] ?: null,
            'statusFilter' => $p['statusFilter'] !== 'all' ? $p['statusFilter'] : null,
            'minRr' => $p['minRr'] > 0 ? $p['minRr'] : null,
            'volOnly' => $p['volOnly'] ? '1' : null,
        ];
    }

    private function cachedScan(array $p): array
    {
        $args = [$p['timeframe'], $p['lookback'], $p['tolerance'], $p['minSeparation'], $p['maxSeparation'], $p['breakoutOnly'], $p['minConfidence'], $p['necklineMinDepth'], $p['statusFilter'], $p['minRr'], $p['volOnly']];
        $key = 'triple-bottom:v1:' . md5(json_encode($args));
        $cached = Yii::$app->cache->get($key);
        if (is_array($cached)) {
            return [$cached, true];
        }
        $service = new TripleBottomService();
        try {
            $results = $service->scanAll(
                $p['timeframe'],
                $p['lookback'],
                $p['tolerance'],
                $p['minSeparation'],
                $p['maxSeparation'],
                $p['breakoutOnly'],
                $p['minConfidence'],
                $p['necklineMinDepth'],
                $p['statusFilter'],
                $p['minRr'],
                $p['volOnly']
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
                usort($results, fn ($a, $b) => abs((float) ($a['distance_neckline_pct'] ?? 999)) <=> abs((float) ($b['distance_neckline_pct'] ?? 999)));
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
        $key = 'triple-bottom:stats';
        $cached = Yii::$app->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }
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
