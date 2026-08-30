<?php

declare(strict_types=1);

namespace app\modules\api\v1\controllers;

use app\models\DailyPrice;
use app\models\Signal;
use app\models\Stock;
use app\models\WeeklyAnalysis;
use yii\data\ActiveDataProvider;

/**
 * GET api/v1/stocks, GET api/v1/stocks/{symbol} (task 13.3).
 */
final class StocksController extends BaseController
{
    public function actionIndex(): ActiveDataProvider
    {
        $query = Stock::find()
            ->where(['active' => true])
            ->andFilterWhere(['like', 'symbol', \Yii::$app->request->get('symbol')])
            ->andFilterWhere(['sector' => \Yii::$app->request->get('sector')])
            ->andFilterWhere(['exchange' => \Yii::$app->request->get('exchange')])
            ->orderBy(['symbol' => SORT_ASC]);

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => min(100, max(1, (int) (\Yii::$app->request->get('limit', 20))))],
        ]);
    }

    public function actionView(string $symbol): array
    {
        $stock = Stock::find()->where(['symbol' => strtoupper($symbol), 'active' => true])->one();
        if ($stock === null) {
            throw new \yii\web\NotFoundHttpException("Symbol {$symbol} not found");
        }

        $weekly = WeeklyAnalysis::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['week_start' => SORT_DESC])
            ->one();

        $signal = Signal::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['date' => SORT_DESC])
            ->one();

        $barCount = (int) (\Yii::$app->request->get('bars', 30));
        $bars = DailyPrice::find()
            ->select(['date', 'open', 'high', 'low', 'close', 'volume'])
            ->where(['stock_id' => $stock->id])
            ->orderBy(['date' => SORT_DESC])
            ->limit(max(1, min(365, $barCount)))
            ->asArray()
            ->all();

        return [
            'stock' => $stock->getAttributes(['symbol', 'name', 'exchange', 'sector', 'industry', 'price', 'market_cap']),
            'latestWeekly' => $weekly?->getAttributes(['week_start', 'buy_ratio', 'sell_ratio', 'volume_growth', 'rvol', 'ma20', 'ma50', 'close_price', 'score', 'signal']),
            'latestSignal' => $signal?->getAttributes(['date', 'price', 'score', 'signal', 'reason']),
            'bars' => array_reverse($bars),
        ];
    }
}
