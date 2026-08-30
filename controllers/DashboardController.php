<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Signal;
use app\models\WeeklyAnalysis;
use Yii;
use yii\db\Expression;
use yii\db\Query;
use yii\web\Controller;

/**
 * Dashboard — ringkasan pasar (task 5.2, 5.3, 5.4).
 */
final class DashboardController extends Controller
{
    private const CACHE_TTL = 900; // 15 menit — task 5.4

    /** Inner subquery: SELECT id FROM stock — pakai untuk filter orphan di semua query. */
    private function validStockSub(): Query
    {
        return (new Query())->select('id')->from('{{%stock}}');
    }

    public function actionIndex(): string
    {
        $cache = Yii::$app->cache;
        $key = 'dashboard-aggregate';

        $data = $cache->get($key);
        if ($data === false) {
            $data = $this->buildAggregate();
            $cache->set($key, $data, self::CACHE_TTL);
        }

        // top ranked & recent signals — query ringan, tanpa cache
        $data['topRanked'] = WeeklyAnalysis::find()
            ->where(['in', 'stock_id', $this->validStockSub()])
            ->with('stock')
            ->orderBy(['score' => SORT_DESC])
            ->limit(10)
            ->all();
        $data['recentSignals'] = Signal::find()
            ->where(['in', 'stock_id', $this->validStockSub()])
            ->with('stock')
            ->orderBy(['date' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(10)
            ->all();

        return $this->render('index', $data);
    }

    private function buildAggregate(): array
    {
        // agregasi minggu terakhir yang punya data (valid stock only — anti orphan)
        $validStock = $this->validStockSub();
        $lastWeek = WeeklyAnalysis::find()
            ->where(['in', 'stock_id', $validStock])
            ->select(new Expression('MAX(week_start)'))
            ->scalar();

        if ($lastWeek === null) {
            return ['lastWeek' => null, 'marketVolume' => 0, 'buyPressure' => 0.5, 'topBuy' => [], 'topSell' => [], 'signalCount' => []];
        }

        // market volume & pressure agregat minggu tsb
        $agg = WeeklyAnalysis::find()
            ->where(['in', 'stock_id', $validStock])
            ->andWhere(['week_start' => $lastWeek])
            ->select([
                'volume' => new Expression('SUM(buy_volume + sell_volume)'),
                'buy'    => new Expression('SUM(buy_volume)'),
                'sell'   => new Expression('SUM(sell_volume)'),
            ])
            ->asArray()
            ->one();

        $total = (int) ($agg['volume'] ?? 0);
        $buy   = (int) ($agg['buy'] ?? 0);

                        // top buying / selling pressure minggu tsb (eager-load stock; guard null di view)
        $topBuy = WeeklyAnalysis::find()
            ->where(['stock_id' => array_column(Stock::find()->select('id')->asArray()->all(), 'id')])
            ->andWhere(['week_start' => $lastWeek])
            ->with('stock')
            ->orderBy(['buy_ratio' => SORT_DESC])
            ->limit(5)
            ->all();

        $topSell = WeeklyAnalysis::find()
            ->where(['stock_id' => array_column(Stock::find()->select('id')->asArray()->all(), 'id')])
            ->andWhere(['week_start' => $lastWeek])
            ->with('stock')
            ->orderBy(['buy_ratio' => SORT_ASC])
            ->limit(5)
            ->all();

        // distribusi sinyal
        $signalCount = WeeklyAnalysis::find()
            ->select(['signal', 'COUNT(*) AS cnt'])
            ->where(['in', 'stock_id', $validStock])
            ->andWhere(['week_start' => $lastWeek])
            ->groupBy('signal')
            ->asArray()
            ->all();

        return [
            'lastWeek' => $lastWeek,
            'marketVolume' => $total,
            'buyPressure' => $total > 0 ? $buy / $total : 0.5,
            'topBuy' => $topBuy,
            'topSell' => $topSell,
            'signalCount' => $signalCount,
        ];
    }
}

