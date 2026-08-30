<?php

declare(strict_types=1);

namespace app\modules\api\v1\controllers;

use yii\filters\auth\HttpBearerAuth;
use yii\rest\Controller;

/**
 * Base controller API v1: JSON + rate limit (task 13.1, 13.2).
 * Endpoint user-scoped (watchlist/alerts) menambah HttpBearerAuth.
 */
abstract class BaseController extends Controller
{
    protected bool $requiresAuth = false;

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['rateLimit'] = ['class' => \app\filters\RateLimitFilter::class];

        if ($this->requiresAuth) {
            $behaviors['authenticator'] = ['class' => HttpBearerAuth::class];
        }

        return $behaviors;
    }
}
