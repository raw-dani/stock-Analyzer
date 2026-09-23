<?php

declare(strict_types=1);

namespace app\controllers;

use app\assets\ChartAsset;
use app\models\DailyPrice;
use app\models\Signal;
use app\models\Stock;
use app\models\WeeklyAnalysis;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Detail saham: profil, weekly analysis, dan endpoint JSON chart (task 7.2–7.6).
 */
final class StockController extends Controller
{
    public function actionView(string $symbol): string
    {
        $stock = Stock::find()->where(['symbol' => strtoupper($symbol)])->with('weeklyAnalyses')->one();
        if ($stock === null) {
            throw new NotFoundHttpException("Symbol {$symbol} tidak ditemukan.");
        }

        $currentWeekly = WeeklyAnalysis::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['week_start' => SORT_DESC])
            ->one();

        // Signal record terkait minggu berjalan (untuk panel 7.4: daftar reason + confidence)
        $currentSignal = null;
        if ($currentWeekly !== null) {
            $currentSignal = Signal::find()
                ->where(['stock_id' => $stock->id, 'date' => $currentWeekly->week_start])
                ->orderBy(['id' => SORT_DESC])
                ->one();
        }

        $weeklyProvider = new ActiveDataProvider([
            'query' => WeeklyAnalysis::find()
                ->where(['stock_id' => $stock->id])
                ->orderBy(['week_start' => SORT_DESC]),
            'pagination' => ['pageSize' => 8],
        ]);

        ChartAsset::register(Yii::$app->view);

        return $this->render('view', [
            'stock' => $stock,
            'currentWeekly' => $currentWeekly,
            'currentSignal' => $currentSignal,
            'weeklyProvider' => $weeklyProvider,
        ]);
    }

    /**
     * JSON data untuk chart candlestick + volume + buy/sell (task 7.6).
     */
    public function actionChartData(string $symbol): array
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $stock = Stock::find()->where(['symbol' => strtoupper($symbol)])->one();
        if ($stock === null) {
            return ['error' => 'Symbol not found'];
        }

        $rows = DailyPrice::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['date' => SORT_ASC])
            ->asArray()
            ->all();

        $dates = [];
        $kline = [];   // [open, close, lowest, highest]
        $volumes = [];
        // MA window
        $closes = [];
        foreach ($rows as $r) {
            $dates[] = $r['date'];
            $kline[] = [(float)$r['open'], (float)$r['close'], (float)$r['low'], (float)$r['high']];
            $volumes[] = [(int)$r['volume'] <= 0 ? 0 : 1, (int)$r['volume']];
            $closes[] = (float)$r['close'];
        }

        // MA20 & MA50 (simple moving average)
        $ma20 = $this->sma($closes, 20);
        $ma50 = $this->sma($closes, 50);

        // Weekly RVOL & buying pressure trend (task 7.5)
        $weeklyRows = WeeklyAnalysis::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['week_start' => SORT_ASC])
            ->asArray()
            ->all();

        $weeklyDates = [];
        $weeklyRvol = [];
        $weeklyBuyPressure = [];
        foreach ($weeklyRows as $w) {
            $weeklyDates[] = $w['week_start'];
            $weeklyRvol[] = $w['rvol'] !== null ? round((float) $w['rvol'], 2) : null;
            $totalVol = (int) $w['buy_volume'] + (int) $w['sell_volume'];
            $weeklyBuyPressure[] = $totalVol > 0 ? round((int) $w['buy_volume'] / $totalVol, 4) : null;
        }

        return [
            'symbol' => $stock->symbol,
            'name' => $stock->name,
            'dates' => $dates,
            'kline' => $kline,
            'volumes' => $volumes,
            'ma20' => $ma20,
            'ma50' => $ma50,
            'weeklyDates' => $weeklyDates,
            'weeklyRvol' => $weeklyRvol,
            'weeklyBuyPressure' => $weeklyBuyPressure,
        ];
    }

    /**
     * Simple moving average — null untuk periode pertama.
     * @return (float|null)[]
     */
    private function sma(array $values, int $period): array
    {
        $result  = [];
        $window  = [];
        foreach ($values as $v) {
            $window[] = $v;
            if (count($window) > $period) array_shift($window);
            $result[] = count($window) < $period ? null : round(array_sum($window) / count($window), 2);
        }
        return $result;
    }
}
