<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\SectorAnalysisService;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Sector Analysis & Heatmap (task 12.1–12.3).
 */
final class SectorController extends Controller
{
    /**
     * Heatmap sektor (task 12.2).
     */
    public function actionIndex(): string
    {
        $service = new SectorAnalysisService();

        return $this->render('index', [
            'sectors' => $service->sectors(),
        ]);
    }

    /**
     * Drill-down sektor: saham terurut score (task 12.3).
     */
    public function actionView(string $sector): string
    {
        $service = new SectorAnalysisService();

        $valid = in_array($sector, $service->sectorList(), true);
        if (!$valid) {
            throw new NotFoundHttpException("Sektor {$sector} tidak ditemukan.");
        }

        return $this->render('view', [
            'sector' => $sector,
            'rows' => $service->stocksBySector($sector),
        ]);
    }
}
