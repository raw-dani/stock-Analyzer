<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\form\SettingsForm;
use app\models\User;
use app\services\MarketStatusService;
use app\services\SettingsService;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

/**
 * Settings & Admin (Modul 14) — HANYA admin (RBAC 14.2).
 * actionIndex  : form pengaturan provider/threshold/channel (14.1).
 * actionStatus : status koneksi provider & waktu update terakhir (14.3).
 */
final class SettingsController extends Controller
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

    public function actionIndex(): Response|string
    {
        $service = Yii::$container->get(SettingsService::class);
        $form = new SettingsForm();

        // Buang field kosong agar tidak menimpa properti numeric nullable
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $form->load($post);
            if ($form->validate()) {
                $form->saveTo($service);
                Yii::$app->session->setFlash('success', 'Pengaturan disimpan.');
                return $this->redirect(['settings/index']);
            }
        } else {
            $form->loadCurrent($service);
        }

        return $this->render('index', [
            'model' => $form,
            'status' => Yii::$container->get(MarketStatusService::class)->footerSummary(),
        ]);
    }

    public function actionStatus(): string
    {
        $status = Yii::$container->get(MarketStatusService::class)->footerSummary();

        return $this->render('status', ['status' => $status]);
    }
}