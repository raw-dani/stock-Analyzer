<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\form\BacktestForm;
use app\models\BacktestRun;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backtesting web (task 11.5): daftar run, form jalankan backtest, detail hasil + trades.
 */
final class BacktestController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $provider = new ActiveDataProvider([
            'query' => BacktestRun::find()->orderBy(['created_at' => SORT_DESC]),
            'pagination' => ['pageSize' => 15],
        ]);

        return $this->render('index', ['dataProvider' => $provider]);
    }

    public function actionCreate(): Response|string
    {
        $form = new BacktestForm();

        $post = (array) Yii::$app->request->post('BacktestForm', []);
        // buang field kosong agar tidak menimpa properti typed nullable
        foreach (['name', 'minBuyRatio', 'minRvol', 'minScore', 'startDate', 'endDate'] as $k) {
            if (isset($post[$k]) && $post[$k] === '') {
                unset($post[$k]);
            }
        }
        if ($form->load($post, '') && $form->validate()) {
            $run = Yii::$container->get(\app\services\BacktesterService::class)->run($form);
            Yii::$app->session->setFlash('success', 'Backtest selesai: ' . $run->trades . ' trades.');

            return $this->redirect(['view', 'id' => $run->id]);
        }

        return $this->render('create', ['model' => $form]);
    }

    public function actionView(int $id): string
    {
        $run = BacktestRun::find()->where(['id' => $id])->with('tradesRel.stock')->one();
        if ($run === null) {
            throw new NotFoundHttpException('Backtest run tidak ditemukan.');
        }

        return $this->render('view', ['run' => $run]);
    }
}
