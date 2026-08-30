<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\form\ScanFilterForm;
use app\services\ScannerService;
use Yii;
use yii\web\Controller;

/**
 * Stock scanner — filter + GridView (task 6.3).
 */
final class ScannerController extends Controller
{
    public function actionIndex(): string
    {
        $form = new ScanFilterForm();
        $form->load(Yii::$app->request->get(), '');

        $dataProvider = Yii::$container->get(ScannerService::class)->search($form);

        return $this->render('index', [
            'searchModel' => $form,
            'dataProvider' => $dataProvider,
        ]);
    }
}

