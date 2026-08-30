<?php

declare(strict_types=1);

namespace app\modules\api\v1\controllers;

use app\models\Alert;
use Yii;

/**
 * GET api/v1/alerts — alert milik user (Bearer token, task 13.2, 13.3).
 */
final class AlertsController extends BaseController
{
    protected bool $requiresAuth = true;

    public function actionIndex(): array
    {
        $alerts = Alert::find()
            ->where(['user_id' => Yii::$app->user->id])
            ->with('stock')
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return [
            'items' => array_map(static fn (Alert $a) => [
                'id' => $a->id,
                'symbol' => $a->stock->symbol ?? null, // null = semua simbol
                'condition_type' => $a->condition_type,
                'operator' => $a->operator,
                'threshold' => (float) $a->threshold,
                'active' => (bool) $a->active,
                'last_triggered_at' => $a->last_triggered_at !== null
                    ? date('c', (int) $a->last_triggered_at)
                    : null,
            ], $alerts),
        ];
    }
}
