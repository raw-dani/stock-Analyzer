<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\Signal;
use app\models\Stock;
use app\models\WeeklyAnalysis;
use yii\base\BaseObject;
use Yii;

/**
 * MarketStatusService (Modul 14.3) — status koneksi provider & waktu update
 * terakhir, ditampilkan di footer layout.
 */
class MarketStatusService extends BaseObject
{
    /** Provider aktif + apakah siap dipakai (HTTP provider butuh API key). */
    public function providerStatus(): array
    {
        $provider = Yii::$container->get(SettingsService::class)->provider();
        $configured = true;

        if ($provider !== 'csv') {
            $key = Yii::$app->params['marketDataProviderConfig'][$provider]['apiKey'] ?? '';
            $configured = $key !== '';
        }

        return [
            'name' => $provider,
            'configured' => $configured,
        ];
    }

    public function providerLabel(): string
    {
        return match ($this->providerStatus()['name']) {
            'alpha_vantage' => 'Alpha Vantage',
            'polygon' => 'Polygon',
            'csv' => 'CSV (dev)',
            default => '—',
        };
    }

    public function lastDataAt(): ?int
    {
        return (int) DailyPrice::find()->max('updated_at') ?: null;
    }

    public function lastAnalyzedAt(): ?int
    {
        return (int) WeeklyAnalysis::find()->max('updated_at') ?: null;
    }

    public function lastSignalDate(): ?string
    {
        return (string) Signal::find()->max('date') ?: null;
    }

    public function stockCount(): int
    {
        return (int) Stock::find()->count();
    }

    /** Ringkasan status untuk footer (string HTML aman). */
    public function footerSummary(): array
    {
        $status = $this->providerStatus();

        return [
            'providerLabel' => $this->providerLabel(),
            'providerConfigured' => $status['configured'],
            'lastDataAt' => $this->lastDataAt(),
            'lastAnalyzedAt' => $this->lastAnalyzedAt(),
            'lastSignalDate' => $this->lastSignalDate(),
            'stockCount' => $this->stockCount(),
        ];
    }
}