<?php

declare(strict_types=1);

namespace app\components;

use app\services\SettingsService;
use yii\base\BootstrapInterface;
use Yii;

/**
 * SettingsBootstrap (Modul 14.1) — menimpakan override settings (tabel
 * {{%setting}}) ke Yii::$app->params saat aplikasi boot.
 *
 * Ini memastikan komponen yg membaca params (SignalEngineService →
 * signalThresholds, DataProviderFactory → marketDataProvider, RateLimitFilter,
 * AlertService) otomatis memakai nilai yang disimpan admin via halaman
 * settings, tanpa mengubah cara baca mereka.
 */
class SettingsBootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        try {
            $s = Yii::$container->get(SettingsService::class);
            $app->params['marketDataProvider'] = $s->provider();
            $app->params['signalThresholds'] = $s->signalThresholds();
            $app->params['alertCooldownHours'] = $s->alertCooldownHours();
            $app->params['apiRateLimitPerMinute'] = $s->apiRateLimitPerMinute();
            $app->params['historyDays'] = $s->historyDays();
        } catch (\Throwable $e) {
            // Tabel setting belum ada (belum migrate) — pakai default params saja.
            Yii::debug('SettingsBootstrap skipped: ' . $e->getMessage());
        }
    }
}