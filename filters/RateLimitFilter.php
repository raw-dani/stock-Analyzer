<?php

declare(strict_types=1);

namespace app\filters;

use Yii;
use yii\base\ActionFilter;
use yii\web\TooManyRequestsHttpException;

/**
 * Rate limit sederhana per klien (Bearer token atau IP) — sliding window
 * per menit via cache (task 13.2). Limit dari params apiRateLimitPerMinute.
 */
final class RateLimitFilter extends ActionFilter
{
    public function beforeAction($action): bool
    {
        $limit = (int) (Yii::$app->params['apiRateLimitPerMinute'] ?? 120);
        $client = Yii::$app->request->getHeaders()->get('Authorization', Yii::$app->request->userIP);
        $key = 'api-rate-' . md5((string) $client) . '-' . (int) (time() / 60);

        $count = (int) Yii::$app->cache->get($key);
        if ($count >= $limit) {
            throw new TooManyRequestsHttpException("Rate limit exceeded ({$limit}/minute).");
        }

        Yii::$app->cache->set($key, $count + 1, 61);
        Yii::$app->response->headers->set('X-Rate-Limit-Limit', (string) $limit);
        Yii::$app->response->headers->set('X-Rate-Limit-Remaining', (string) max(0, $limit - $count - 1));

        return parent::beforeAction($action);
    }
}
