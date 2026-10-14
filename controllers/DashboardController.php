<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\DailyPrice;
use app\models\PortfolioPosition;
use app\models\Signal;
use app\models\Stock;
use app\models\WeeklyAnalysis;
use app\services\MultiIndicatorStrategyService;
use Yii;
use yii\db\Expression;
use yii\db\Query;
use yii\web\Controller;

/**
 * Dashboard — Pusat Ringkasan Pasar, Analisis Multi-Indikator, Sinyal Reversal & Portofolio.
 */
final class DashboardController extends Controller
{
    private const CACHE_TTL = 900; // 15 menit

    /** Inner subquery: SELECT id FROM stock — pakai untuk filter orphan di semua query. */
    private function validStockSub(): Query
    {
        return (new Query())->select('id')->from('{{%stock}}');
    }

    public function actionIndex(): string
    {
        $cache = Yii::$app->cache;
        $key = 'dashboard-aggregate-v2';

        $data = $cache->get($key);
        if ($data === false) {
            $data = $this->buildAggregate();
            $cache->set($key, $data, self::CACHE_TTL);
        }

        $validStocks = $this->validStockSub();

        // 1. Top Ranked (Score Kuantitatif Akumulasi Institusional)
        $data['topRanked'] = WeeklyAnalysis::find()
            ->where(['in', 'stock_id', $validStocks])
            ->with('stock')
            ->orderBy(['score' => SORT_DESC])
            ->limit(10)
            ->all();

        // 2. Sinyal BUY & STRONG BUY Terkini
        $data['recentBuySignals'] = Signal::find()
            ->where(['in', 'stock_id', $validStocks])
            ->andWhere(['signal' => ['STRONG_BUY', 'BUY']])
            ->with('stock')
            ->orderBy(['date' => SORT_DESC, 'score' => SORT_DESC])
            ->limit(10)
            ->all();

        // Fallback jika belum ada sinyal buy
        if (empty($data['recentBuySignals'])) {
            $data['recentBuySignals'] = Signal::find()
                ->where(['in', 'stock_id', $validStocks])
                ->with('stock')
                ->orderBy(['date' => SORT_DESC, 'id' => SORT_DESC])
                ->limit(10)
                ->all();
        }

        // 3. Portofolio ringkasan user saat ini (jika login)
        $portfolioData = [
            'isGuest' => Yii::$app->user->isGuest,
            'count' => 0,
            'invested' => 0.0,
            'currentValue' => 0.0,
            'profitCount' => 0,
            'lossCount' => 0,
            'positions' => [],
        ];

        if (!Yii::$app->user->isGuest) {
            $userId = (int) Yii::$app->user->id;
            $positions = PortfolioPosition::find()
                ->where(['user_id' => $userId])
                ->with('stock')
                ->orderBy(['updated_at' => SORT_DESC])
                ->all();

            $portfolioData['count'] = count($positions);
            foreach ($positions as $p) {
                $portfolioData['invested'] += $p->getTotalCost();
                $portfolioData['currentValue'] += $p->getCurrentValue();
                if ($p->isProfit()) {
                    $portfolioData['profitCount']++;
                } elseif ($p->isLoss()) {
                    $portfolioData['lossCount']++;
                }
            }
            $portfolioData['positions'] = array_slice($positions, 0, 5);
        }
        $data['portfolioData'] = $portfolioData;

        // 4. Quick Confluence Picks (Multi-Indikator Pro: Golden Pocket, RSI, MACD)
        $confluenceKey = 'dashboard-confluence-picks';
        $confluencePicks = $cache->get($confluenceKey);
        if ($confluencePicks === false) {
            $confluencePicks = $this->buildConfluencePicks();
            $cache->set($confluenceKey, $confluencePicks, self::CACHE_TTL);
        }
        $data['confluencePicks'] = $confluencePicks;

        return $this->render('index', $data);
    }

    private function buildAggregate(): array
    {
        $validStock = $this->validStockSub();

        // 1. Cari tanggal harian likuid terakhir
        $lastDailyDate = DailyPrice::find()
            ->select('date')
            ->groupBy('date')
            ->having('COUNT(*) > 50')
            ->orderBy(['date' => SORT_DESC])
            ->scalar();

        $dailyStats = [
            'date' => $lastDailyDate,
            'totalStocks' => 0,
            'gainersCount' => 0,
            'losersCount' => 0,
            'totalVolume' => 0,
            'topGainers' => [],
            'topLosers' => [],
            'topVolumes' => [],
        ];

        if ($lastDailyDate) {
            $dailyRow = DailyPrice::find()
                ->where(['date' => $lastDailyDate])
                ->select([
                    'cnt' => new Expression('COUNT(*)'),
                    'gainers' => new Expression('SUM(CASE WHEN close > open THEN 1 ELSE 0 END)'),
                    'losers' => new Expression('SUM(CASE WHEN close < open THEN 1 ELSE 0 END)'),
                    'totVol' => new Expression('SUM(volume)'),
                ])
                ->asArray()
                ->one();

            $dailyStats['totalStocks'] = (int) ($dailyRow['cnt'] ?? 0);
            $dailyStats['gainersCount'] = (int) ($dailyRow['gainers'] ?? 0);
            $dailyStats['losersCount'] = (int) ($dailyRow['losers'] ?? 0);
            $dailyStats['totalVolume'] = (int) ($dailyRow['totVol'] ?? 0);

            $dailyStats['topGainers'] = DailyPrice::find()
                ->where(['date' => $lastDailyDate])
                ->with('stock')
                ->orderBy(new Expression('((close - open) / open) DESC'))
                ->limit(5)
                ->all();

            $dailyStats['topLosers'] = DailyPrice::find()
                ->where(['date' => $lastDailyDate])
                ->with('stock')
                ->orderBy(new Expression('((close - open) / open) ASC'))
                ->limit(5)
                ->all();

            $dailyStats['topVolumes'] = DailyPrice::find()
                ->where(['date' => $lastDailyDate])
                ->with('stock')
                ->orderBy(['volume' => SORT_DESC])
                ->limit(5)
                ->all();
        }

        // 2. Agregasi mingguan (Institutional Volume Breakdown)
        $lastWeek = WeeklyAnalysis::find()
            ->where(['in', 'stock_id', $validStock])
            ->select(new Expression('MAX(week_start)'))
            ->scalar();

        if ($lastWeek === null) {
            return [
                'lastWeek' => null,
                'marketVolume' => 0,
                'buyPressure' => 0.5,
                'topBuy' => [],
                'topSell' => [],
                'signalCount' => [],
                'dailyStats' => $dailyStats,
                'totalTrackedStocks' => Stock::find()->count(),
            ];
        }

        $agg = WeeklyAnalysis::find()
            ->where(['in', 'stock_id', $validStock])
            ->andWhere(['week_start' => $lastWeek])
            ->select([
                'volume' => new Expression('SUM(buy_volume + sell_volume)'),
                'buy' => new Expression('SUM(buy_volume)'),
                'sell' => new Expression('SUM(sell_volume)'),
            ])
            ->asArray()
            ->one();

        $total = (int) ($agg['volume'] ?? 0);
        $buy = (int) ($agg['buy'] ?? 0);

        $topBuy = WeeklyAnalysis::find()
            ->where(['in', 'stock_id', $validStock])
            ->andWhere(['week_start' => $lastWeek])
            ->with('stock')
            ->orderBy(['buy_ratio' => SORT_DESC])
            ->limit(5)
            ->all();

        $topSell = WeeklyAnalysis::find()
            ->where(['in', 'stock_id', $validStock])
            ->andWhere(['week_start' => $lastWeek])
            ->with('stock')
            ->orderBy(['buy_ratio' => SORT_ASC])
            ->limit(5)
            ->all();

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
            'dailyStats' => $dailyStats,
            'totalTrackedStocks' => Stock::find()->count(),
        ];
    }

    /**
     * Hitung top picks strategi Confluence Multi-Indikator
     */
    private function buildConfluencePicks(): array
    {
        $stocks = Stock::find()->where(['active' => true])->limit(30)->all();
        $svc = new MultiIndicatorStrategyService();
        $picks = [];

        foreach ($stocks as $s) {
            $analysis = $svc->analyzeStock((int) $s->id, ['timeframe' => 1, 'lookback' => 100]);
            if ($analysis !== null) {
                $picks[] = $analysis;
            }
        }

        usort($picks, fn($a, $b) => $b['confluence_score'] <=> $a['confluence_score']);
        return array_slice($picks, 0, 6);
    }
}
