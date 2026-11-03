<?php

declare(strict_types=1);

namespace app\controllers;

use app\assets\ChartAsset;
use app\models\DailyPrice;
use app\models\Signal;
use app\models\Stock;
use app\models\WeeklyAnalysis;
use app\services\StockStrategySynthesisService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

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

        // Signal record: cari sinyal tanggal week_start, jika tidak ada fallback ke sinyal terbaru
        $currentSignal = null;
        if ($currentWeekly !== null) {
            $currentSignal = Signal::find()
                ->where(['stock_id' => $stock->id, 'date' => $currentWeekly->week_start])
                ->orderBy(['id' => SORT_DESC])
                ->one();
        }
        if ($currentSignal === null) {
            $currentSignal = Signal::find()
                ->where(['stock_id' => $stock->id])
                ->orderBy(['date' => SORT_DESC, 'id' => SORT_DESC])
                ->one();
        }

        // Ambil 2 candle harian terakhir untuk kalkulasi harga aktual dan perubahan hari ini
        $recentPrices = DailyPrice::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['date' => SORT_DESC])
            ->limit(2)
            ->asArray()
            ->all();

        $latestDaily = !empty($recentPrices[0]) ? $recentPrices[0] : null;
        $prevDaily = !empty($recentPrices[1]) ? $recentPrices[1] : null;

        $priceStats = null;
        if ($latestDaily !== null) {
            $currClose = (float) $latestDaily['close'];
            $prevClose = $prevDaily !== null ? (float) $prevDaily['close'] : (float) $latestDaily['open'];
            $change = round($currClose - $prevClose, 2);
            $changePct = $prevClose > 0 ? round(($change / $prevClose) * 100, 2) : 0.0;

            $priceStats = [
                'current_price' => $currClose,
                'open' => (float) $latestDaily['open'],
                'high' => (float) $latestDaily['high'],
                'low' => (float) $latestDaily['low'],
                'close' => $currClose,
                'volume' => (int) $latestDaily['volume'],
                'date' => $latestDaily['date'],
                'change' => $change,
                'change_pct' => $changePct,
            ];
        }

        $weeklyProvider = new ActiveDataProvider([
            'query' => WeeklyAnalysis::find()
                ->where(['stock_id' => $stock->id])
                ->orderBy(['week_start' => SORT_DESC]),
            'pagination' => ['pageSize' => 8],
        ]);

        ChartAsset::register(Yii::$app->view);

        // Evaluasi sintesis seluruh strategi (Multi-Strategy Consensus Analysis)
        $requestedStrategy = (string) Yii::$app->request->get('strategy', 'all');
        $synthesisService = new StockStrategySynthesisService();
        $strategyEvaluation = $synthesisService->evaluateStock($stock, $requestedStrategy, [
            'timeframe' => (int) Yii::$app->request->get('timeframe', 24),
        ]);

        return $this->render('view', [
            'stock' => $stock,
            'currentWeekly' => $currentWeekly,
            'currentSignal' => $currentSignal,
            'weeklyProvider' => $weeklyProvider,
            'priceStats' => $priceStats,
            'strategyEvaluation' => $strategyEvaluation,
            'requestedStrategy' => $requestedStrategy,
        ]);
    }

    /**
     * Endpoint redirect pencarian kode saham dari form dashboard atau navbar.
     */
    public function actionSearch(?string $symbol = null): Response
    {
        $symbol = strtoupper(trim((string) ($symbol ?? Yii::$app->request->get('symbol', ''))));
        if ($symbol === '') {
            Yii::$app->session->setFlash('warning', 'Silakan masukkan kode saham yang ingin dicari.');
            return $this->redirect(['/dashboard/index']);
        }

        $stock = Stock::find()->where(['symbol' => $symbol, 'active' => true])->one();
        if ($stock !== null) {
            return $this->redirect(['view', 'symbol' => $stock->symbol]);
        }

        // Coba cari like jika tidak exact match
        $partial = Stock::find()->where(['like', 'symbol', $symbol])->andWhere(['active' => true])->one();
        if ($partial !== null) {
            return $this->redirect(['view', 'symbol' => $partial->symbol]);
        }

        Yii::$app->session->setFlash('danger', "Kode saham '{$symbol}' tidak ditemukan dalam database aplikasi.");
        return $this->redirect(['/dashboard/index']);
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
        $closes = [];

        foreach ($rows as $r) {
            $dates[] = $r['date'];
            $open = (float) $r['open'];
            $close = (float) $r['close'];
            $kline[] = [$open, $close, (float) $r['low'], (float) $r['high']];
            // 1 jika bullish (close >= open), 0 jika bearish (close < open)
            $isBullish = ($close >= $open) ? 1 : 0;
            $volumes[] = [$isBullish, (int) $r['volume']];
            $closes[] = $close;
        }

        // Indikator MA20, MA50, dan MA200
        $ma20 = $this->sma($closes, 20);
        $ma50 = $this->sma($closes, 50);
        $ma200 = $this->sma($closes, 200);

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
            'ma200' => $ma200,
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
