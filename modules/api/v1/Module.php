<?php

declare(strict_types=1);

namespace app\modules\api\v1;

use Yii;
use yii\base\Module as BaseModule;

/**
 * API v1 module — response JSON + error handling via ApiErrorHandler (task 13.1).
 */
final class Module extends BaseModule
{
    public $controllerNamespace = 'app\modules\api\v1\controllers';
    public $defaultRoute = 'stocks';

    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Yii::$app->response::FORMAT_JSON;

        return parent::beforeAction($action);
    }
}
