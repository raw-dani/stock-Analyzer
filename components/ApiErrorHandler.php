<?php

declare(strict_types=1);

namespace app\components;

use Yii;
use yii\web\ErrorHandler;

/**
 * Error handler yang merender JSON untuk request API (task 13.1),
 * HTML untuk sisanya (errorAction site/error).
 */
final class ApiErrorHandler extends ErrorHandler
{
    protected function renderException($exception): void
    {
        $path = null;
        if (Yii::$app->has('request', true)) {
            try {
                $path = (string) Yii::$app->request->getPathInfo();
            } catch (\Throwable $e) {
                $path = null;
            }
        }

        if ($path !== null && str_starts_with($path, 'api/')) {
            $response = Yii::$app->response;
            $response->format = $response::FORMAT_JSON;
            $response->data = $this->convertExceptionToArray($exception);
            $response->setStatusCodeByException($exception);
            $response->send();

            return;
        }

        parent::renderException($exception);
    }
}
