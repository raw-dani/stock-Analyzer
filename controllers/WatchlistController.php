<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Stock;
use app\models\Watchlist;
use app\models\WatchlistItem;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Watchlist CRUD + item management (task 8.1–8.3).
 * Semua aksi hanya untuk user yang login (role @).
 * user_id scoping otomatis agar user hanya melihat/mengelola watchlist-nya sendiri.
 */
final class WatchlistController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => false,
                        'roles' => ['?'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Daftar watchlist milik user (task 8.1).
     */
    public function actionIndex(): string
    {
        $watchlists = Watchlist::find()
            ->where(['user_id' => Yii::$app->user->id])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        return $this->render('index', [
            'watchlists' => $watchlists,
        ]);
    }

    /**
     * Detail watchlist: tabel Symbol, Price, Buy Ratio, RVOL, Score, Signal (task 8.3).
     */
    public function actionView(int $id): string
    {
        $watchlist = $this->findWatchlist($id);

        $items = WatchlistItem::find()
            ->where(['watchlist_id' => $watchlist->id])
            ->with(['stock', 'latestWeekly'])
            ->all();

        return $this->render('view', [
            'watchlist' => $watchlist,
            'items' => $items,
        ]);
    }

    /**
     * Buat watchlist baru (task 8.1).
     */
    public function actionCreate(): Response|string
    {
        $model = new Watchlist();
        $model->user_id = Yii::$app->user->id;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('_form', ['model' => $model]);
    }

    /**
     * Update watchlist (task 8.1).
     */
    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findWatchlist($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('_form', ['model' => $model]);
    }

    /**
     * Hapus watchlist + semua item-nya (task 8.1).
     */
    public function actionDelete(int $id): Response
    {
        $watchlist = $this->findWatchlist($id);

        WatchlistItem::deleteAll(['watchlist_id' => $watchlist->id]);
        $watchlist->delete();

        return $this->redirect(['index']);
    }

    /**
     * Tambah saham ke watchlist (task 8.2).
     * Accept: POST with `symbol`. Redirect ke view page.
     */
    public function actionAddItem(int $id): Response
    {
        $watchlist = $this->findWatchlist($id);

        $symbol = strtoupper((string) Yii::$app->request->post('symbol'));
        if ($symbol === '') {
            Yii::$app->session->setFlash('error', 'Symbol tidak boleh kosong.');
            return $this->redirect(['view', 'id' => $watchlist->id]);
        }

        $stock = Stock::find()->where(['symbol' => $symbol, 'active' => true])->one();
        if ($stock === null) {
            Yii::$app->session->setFlash('error', "Symbol {$symbol} tidak ditemukan.");
            return $this->redirect(['view', 'id' => $watchlist->id]);
        }

        $exists = WatchlistItem::find()
            ->where(['watchlist_id' => $watchlist->id, 'stock_id' => $stock->id])
            ->exists();
        if ($exists) {
            Yii::$app->session->setFlash('warning', "{$symbol} sudah ada di watchlist.");
            return $this->redirect(['view', 'id' => $watchlist->id]);
        }

        $item = new WatchlistItem();
        $item->watchlist_id = $watchlist->id;
        $item->stock_id = $stock->id;
        $item->created_at = time();
        $item->save();

        return $this->redirect(['view', 'id' => $watchlist->id]);
    }

    /**
     * Hapus item dari watchlist (task 8.2).
     */
    public function actionRemoveItem(int $id): Response
    {
        $item = WatchlistItem::findOne($id);
        if ($item === null) {
            throw new NotFoundHttpException('Item tidak ditemukan.');
        }

        // pastikan item milik watchlist user ini
        $this->findWatchlist($item->watchlist_id);
        $item->delete();

        return $this->redirect(['view', 'id' => $item->watchlist_id]);
    }

    /**
     * Symbol autocomplete untuk pencarian saham (dipakai add-item form).
     */
    public function actionSymbolSearch(string $q): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $results = Stock::find()
            ->where(['active' => true])
            ->andWhere(['like', 'symbol', $q])
            ->select(['id', 'symbol', 'name'])
            ->limit(10)
            ->asArray()
            ->all();

        return array_map(fn ($r) => [
            'id' => $r['id'],
            'text' => $r['symbol'] . ' — ' . $r['name'],
            'symbol' => $r['symbol'],
        ], $results);
    }

    private function findWatchlist(int $id): Watchlist
    {
        $model = Watchlist::find()
            ->where(['id' => $id, 'user_id' => Yii::$app->user->id])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('Watchlist tidak ditemukan.');
        }

        return $model;
    }
}
