<?php

declare(strict_types=1);

namespace app\modules\api\v1\controllers;

use app\models\form\ScanFilterForm;
use app\models\WeeklyAnalysis;
use app\services\ScannerService;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * GET api/v1/scanner — hasil scanner terurut score (task 13.3).
 */
final class ScannerController extends BaseController
{
    public function actionIndex(): array
    {
        $form = new ScanFilterForm();
        $query = Yii::$app->request->get();
        $query = array_filter($query, fn ($v) => $v !== '' && $v !== null);
        unset($query['page']);
        $form->load($query, '');

        $dataProvider = Yii::$container->get(ScannerService::class)->search($form);
        $dataProvider->pagination->pageSize = min(100, max(1, (int) Yii::$app->request->get('limit', $form->limit ?? 20)));

        return [
            'criteria' => $form->getAttributes(),
            'items' => array_map(static fn (WeeklyAnalysis $w) => [
                'symbol' => $w->stock->symbol,
                'name' => $w->stock->name,
                'sector' => $w->stock->sector,
                'price' => $w->stock->price,
                'buy_ratio' => $w->buy_ratio,
                'volume_growth' => $w->volume_growth,
                'rvol' => $w->rvol,
                'score' => $w->score,
                'signal' => $w->signal,
                'week_start' => $w->week_start,
            ], $dataProvider->getModels()),
            'totalCount' => $dataProvider->getTotalCount(),
        ];
    }
}
