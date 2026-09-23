<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\DoubleBottomService;
use Yii;
use yii\web\Controller;

/**
 * Double Bottom Pattern Scanner - deteksi pola double bottom pada berbagai timeframe.
 */
final class DoubleBottomController extends Controller
{
    /**
     * Index action - tampilkan hasil scan double bottom.
     */
    public function actionIndex(): string
    {
        $timeframe = (int) Yii::$app->request->get('timeframe', DoubleBottomService::TIMEFRAME_4H);
        $lookbackDays = (int) Yii::$app->request->get('lookback', 30);
        $tolerancePercent = (float) Yii::$app->request->get('tolerance', 2);

        // Convert tolerance from percentage to decimal (e.g., 2% = 0.02)
        $tolerance = $tolerancePercent / 100;

        // Validate timeframe
        $timeframes = DoubleBottomService::getTimeframes();
        if (!isset($timeframes[$timeframe])) {
            $timeframe = DoubleBottomService::TIMEFRAME_4H;
        }

        Yii::info("Double Bottom Scan: timeframe={$timeframe}, lookback={$lookbackDays}, tolerance={$tolerance}", 'app\services\doublebottom');

        $service = new DoubleBottomService();
        $results = [];
        try {
            $results = $service->scanAll($timeframe, $lookbackDays, $tolerance);
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('warning', $e->getMessage());
        }

        Yii::info("Double Bottom Scan: found " . count($results) . " patterns", 'app\services\doublebottom');

        return $this->render('index', [
            'results' => $results,
            'timeframe' => $timeframe,
            'lookbackDays' => $lookbackDays,
            'tolerance' => $tolerance,
            'timeframes' => $timeframes,
        ]);
    }

    /**
     * Detail action - tampilkan detail pattern untuk satu simbol.
     * Meneruskan tolerance agar konsisten dengan hasil tabel index.
     */
    public function actionDetail(string $symbol): string
    {
        $timeframe = (int) Yii::$app->request->get('timeframe', DoubleBottomService::TIMEFRAME_4H);
        $lookbackDays = (int) Yii::$app->request->get('lookback', 30);
        $tolerancePercent = (float) Yii::$app->request->get('tolerance', 2);
        $tolerance = $tolerancePercent / 100;

        $stock = \app\models\Stock::find()->where(['symbol' => $symbol])->one();
        if ($stock === null) {
            throw new \yii\web\NotFoundHttpException("Stock {$symbol} not found.");
        }

        $service = new DoubleBottomService();
        $pattern = null;
        try {
            $pattern = $service->detectPattern($stock->id, $timeframe, $lookbackDays, $tolerance);
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('warning', $e->getMessage());
        }

        return $this->render('detail', [
            'stock' => $stock,
            'pattern' => $pattern,
            'timeframe' => $timeframe,
            'lookbackDays' => $lookbackDays,
            'tolerance' => $tolerance,
            'timeframes' => DoubleBottomService::getTimeframes(),
        ]);
    }
}