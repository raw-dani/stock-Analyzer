<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\MarketStatusService;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Health endpoint (Modul 15.5) — dipakai load balancer / monitoring.
 * GET /health → JSON { status, db, queue, provider, lastDataAt, timestamp }.
 */
final class HealthController extends Controller
{
    public function actionIndex(): Response
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;

        $data = [
            'status' => 'ok',
            'timestamp' => time(),
            'db' => $this->checkDb(),
            'queue' => get_class(Yii::$app->queue),
            'provider' => '',
            'lastDataAt' => null,
        ];

        try {
            $mkt = Yii::$container->get(MarketStatusService::class)->footerSummary();
            $data['provider'] = $mkt['providerLabel'];
            $data['providerConfigured'] = $mkt['providerConfigured'];
            $data['lastDataAt'] = $mkt['lastDataAt'];
            $data['stockCount'] = $mkt['stockCount'];
        } catch (\Throwable $e) {
            $data['provider'] = 'error';
        }

        // Jika DB down → status 503 (memancing alarm monitoring).
        if ($data['db'] !== 'ok') {
            $response->statusCode = 503;
            $data['status'] = 'degraded';
        }

        return $response->data = $data;
    }

    private function checkDb(): string
    {
        try {
            Yii::$app->db->open();
            Yii::$app->db->createCommand('SELECT 1')->queryScalar();

            return 'ok';
        } catch (\Throwable $e) {
            Yii::error('Health DB check failed: ' . $e->getMessage(), __METHOD__);

            return 'error';
        }
    }
}