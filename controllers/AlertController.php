<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Alert;
use app\models\AlertLog;
use app\models\form\AlertForm;
use app\models\Stock;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Alert System web (task 10.1, 10.5, 10.8).
 * CRUD alert per user + halaman notifikasi in-app (list + read).
 */
final class AlertController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => false, 'roles' => ['?']],
                    ['allow' => true, 'roles' => ['@']],
                ],
            ],
        ];
    }

    /**
     * Daftar alert milik user + jumlah notifikasi belum dibaca (task 10.1).
     */
    public function actionIndex(): string
    {
        $provider = new ActiveDataProvider([
            'query' => Alert::find()
                ->where(['user_id' => Yii::$app->user->id])
                ->orderBy(['created_at' => SORT_DESC]),
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', [
            'dataProvider' => $provider,
            'unreadCount' => $this->unreadCount(),
        ]);
    }

    public function actionCreate(): Response|string
    {
        $form = new AlertForm();

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            $alert = new Alert();
            $alert->user_id = (int) Yii::$app->user->id;
            $alert->stock_id = $this->resolveStockId($form->symbol);
            $alert->condition_type = $form->condition_type;
            $alert->operator = $form->operator;
            $alert->threshold = $form->threshold;
            $alert->active = $form->active;
            if ($alert->save()) {
                Yii::$app->session->setFlash('success', 'Alert dibuat.');
                return $this->redirect(['index']);
            }
            $form->addErrors($alert->getErrors());
        }

        return $this->render('create', ['model' => $form]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $alert = $this->findAlert($id);
        $form = new AlertForm();
        $form->condition_type = $alert->condition_type;
        $form->operator = $alert->operator;
        $form->threshold = (float) $alert->threshold;
        $form->active = (bool) $alert->active;
        $form->symbol = $alert->stock->symbol ?? null;

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            $alert->stock_id = $this->resolveStockId($form->symbol);
            $alert->condition_type = $form->condition_type;
            $alert->operator = $form->operator;
            $alert->threshold = $form->threshold;
            $alert->active = $form->active;
            if ($alert->save()) {
                Yii::$app->session->setFlash('success', 'Alert diperbarui.');
                return $this->redirect(['index']);
            }
        }

        return $this->render('update', ['model' => $form]);
    }

    public function actionDelete(int $id): Response
    {
        $alert = $this->findAlert($id);
        AlertLog::deleteAll(['alert_id' => $alert->id]);
        $alert->delete();
        Yii::$app->session->setFlash('success', 'Alert dihapus.');

        return $this->redirect(['index']);
    }

    public function actionToggle(int $id): Response
    {
        $alert = $this->findAlert($id);
        $alert->active = !$alert->active;
        $alert->save(false, ['active']);

        return $this->redirect(['index']);
    }

    /**
     * In-app notification: list + read (task 10.5, 10.8).
     */
    public function actionNotifications(): string
    {
        $provider = new ActiveDataProvider([
            'query' => AlertLog::find()
                ->joinWith(['alert'])
                ->where(['{{%alert}}.user_id' => Yii::$app->user->id])
                ->andWhere(['{{%alert_log}}.channel' => AlertLog::CHANNEL_APP])
                ->orderBy(['{{%alert_log}}.created_at' => SORT_DESC]),
            'pagination' => ['pageSize' => 25],
        ]);

        return $this->render('notifications', [
            'dataProvider' => $provider,
            'unreadCount' => $this->unreadCount(),
        ]);
    }

    public function actionRead(int $id): Response
    {
        $log = AlertLog::find()
            ->joinWith(['alert'])
            ->where(['{{%alert_log}}.id' => $id, '{{%alert}}.user_id' => Yii::$app->user->id])
            ->one();
        if ($log === null) {
            throw new NotFoundHttpException('Notifikasi tidak ditemukan.');
        }

        if ($log->read_at === null) {
            $log->read_at = time();
            $log->save(false, ['read_at']);
        }

        return $this->redirect(['notifications']);
    }

    public function actionMarkAllRead(): Response
    {
        AlertLog::updateAll(
            ['read_at' => time()],
            [
                'channel' => AlertLog::CHANNEL_APP,
                'read_at' => null,
                'alert_id' => Alert::find()->select('id')->where(['user_id' => Yii::$app->user->id]),
            ],
        );

        return $this->redirect(['notifications']);
    }

    private function unreadCount(): int
    {
        return (int) AlertLog::find()
            ->joinWith(['alert'])
            ->where(['{{%alert}}.user_id' => Yii::$app->user->id])
            ->andWhere(['{{%alert_log}}.channel' => AlertLog::CHANNEL_APP])
            ->andWhere(['{{%alert_log}}.read_at' => null])
            ->count();
    }

    private function resolveStockId(?string $symbol): ?int
    {
        if ($symbol === null || $symbol === '') {
            return null;
        }

        return (int) Stock::find()->select('id')->where(['symbol' => strtoupper($symbol)])->scalar();
    }

    private function findAlert(int $id): Alert
    {
        $alert = Alert::find()->where(['id' => $id, 'user_id' => Yii::$app->user->id])->one();
        if ($alert === null) {
            throw new NotFoundHttpException('Alert tidak ditemukan.');
        }

        return $alert;
    }
}
