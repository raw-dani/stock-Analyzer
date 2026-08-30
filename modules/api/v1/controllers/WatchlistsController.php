<?php

declare(strict_types=1);

namespace app\modules\api\v1\controllers;

use app\models\Watchlist;
use Yii;

/**
 * GET api/v1/watchlist — watchlist user (Bearer token, task 13.2, 13.3).
 */
final class WatchlistsController extends BaseController
{
    protected bool $requiresAuth = true;

    public function actionIndex(): array
    {
        $watchlists = Watchlist::find()
            ->where(['user_id' => Yii::$app->user->id])
            ->with(['items.stock'])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        return [
            'items' => array_map(static fn (Watchlist $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'description' => $w->description,
                'stocks' => array_map(static fn ($item) => [
                    'symbol' => $item->stock->symbol ?? null,
                    'price' => $item->stock->price ?? null,
                ], $w->items),
            ], $watchlists),
        ];
    }
}
