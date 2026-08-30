<?php

declare(strict_types=1);

namespace app\modules\api\v1\controllers;

use app\models\Signal;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * GET api/v1/signals — histori sinyal (task 13.3).
 */
final class SignalsController extends BaseController
{
    public function actionIndex(): array
    {
        $query = Signal::find()
            ->joinWith(['stock'])
            ->andWhere(['{{%stock}}.active' => true])
            ->andFilterWhere(['like', '{{%stock}}.symbol', Yii::$app->request->get('symbol')])
            ->andFilterWhere(['{{%signal}}.signal' => Yii::$app->request->get('signal')])
            ->andFilterWhere(['>=', '{{%signal}}.date', Yii::$app->request->get('dateFrom')])
            ->andFilterWhere(['<=', '{{%signal}}.date', Yii::$app->request->get('dateTo')])
            ->orderBy(['{{%signal}}.date' => SORT_DESC, '{{%signal}}.id' => SORT_DESC]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => min(100, max(1, (int) Yii::$app->request->get('limit', 20)))],
        ]);

        return [
            'items' => array_map(static fn (Signal $s) => [
                'symbol' => $s->stock->symbol,
                'date' => $s->date,
                'price' => $s->price,
                'score' => $s->score,
                'signal' => $s->signal,
                'buy_ratio' => $s->buy_ratio,
                'rvol' => $s->rvol,
                'volume_growth' => $s->volume_growth,
                'reasons' => $s->getReasonList(),
            ], $provider->getModels()),
            'totalCount' => $provider->getTotalCount(),
        ];
    }
}
