<?php

declare(strict_types=1);

namespace app\services;

use app\models\Setting;
use Yii;
use yii\base\BaseObject;

/**
 * SettingsService (Modul 14.1) — baca/tulis pengaturan aplikasi.
 *
 * Nilai default diambil dari config/params.php; nilai yang disimpan admin
 * via halaman settings di tabel {{%setting}} akan menimpa default tersebut.
 * Karena sinyal/scoring diambil dari params saat runtime (SignalEngine,
 * DataProviderFactory, AlertService), halaman settings menyimpan override di
 * DB — service ini menyatukan keduanya.
 */
class SettingsService extends BaseObject
{
    /** Maksimal seed nilai threshold per indikator. */
    private const TIER_COUNT = 3;

    // ==== Default (fallback = config/params.php) ====

    private function param(string $key, $default = null)
    {
        return Yii::$app->params[$key] ?? $default;
    }

    private function read(string $key, string $type, $default)
    {
        $raw = Setting::getValue($key);
        if ($raw === null) {
            return $default;
        }

        return match ($type) {
            Setting::TYPE_INT => (int) $raw,
            Setting::TYPE_FLOAT => (float) $raw,
            Setting::TYPE_BOOL => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            Setting::TYPE_JSON => json_decode($raw, true) ?: $default,
            default => $raw,
        };
    }

    // ==== Provider (14.1) ====

    public function provider(): string
    {
        return (string) $this->read('marketDataProvider', Setting::TYPE_STRING, $this->param('marketDataProvider', 'csv'));
    }

    public function setProvider(string $provider): void
    {
        $this->write('marketDataProvider', 'Provider data pasar', Setting::TYPE_STRING, 'provider', $provider);
    }

    // ==== Signal thresholds (14.1) ====

    public function signalThresholds(): array
    {
        return $this->read('signalThresholds', Setting::TYPE_JSON, $this->param('signalThresholds', []));
    }

    public function setSignalThresholds(array $thresholds): void
    {
        $this->write('signalThresholds', 'Threshold scoring signal', Setting::TYPE_JSON, 'scoring', json_encode($thresholds, JSON_UNESCAPED_SLASHES));
    }

    // ==== Notification channels (14.1) ====

    public function notificationChannels(): array
    {
        return [
            'app' => $this->read('channel_app', Setting::TYPE_BOOL, true),
            'email' => $this->read('channel_email', Setting::TYPE_BOOL, true),
            'telegram' => $this->read('channel_telegram', Setting::TYPE_BOOL, true),
        ];
    }

    public function setNotificationChannel(string $channel, bool $enabled): void
    {
        $this->write(
            "channel_{$channel}",
            "Channel notifikasi {$channel}",
            Setting::TYPE_BOOL,
            'notification',
            $enabled ? '1' : '0'
        );
    }

    // ==== System (14.1) ====

    public function alertCooldownHours(): int
    {
        return (int) $this->read('alertCooldownHours', Setting::TYPE_INT, $this->param('alertCooldownHours', 24));
    }

    public function setAlertCooldownHours(int $hours): void
    {
        $this->write('alertCooldownHours', 'Alert cooldown (jam)', Setting::TYPE_INT, 'system', (string) $hours);
    }

    public function apiRateLimitPerMinute(): int
    {
        return (int) $this->read('apiRateLimitPerMinute', Setting::TYPE_INT, $this->param('apiRateLimitPerMinute', 120));
    }

    public function setApiRateLimitPerMinute(int $limit): void
    {
        $this->write('apiRateLimitPerMinute', 'Rate limit API per menit', Setting::TYPE_INT, 'system', (string) $limit);
    }

    public function historyDays(): int
    {
        return (int) $this->read('historyDays', Setting::TYPE_INT, $this->param('historyDays', 400));
    }

    public function setHistoryDays(int $days): void
    {
        $this->write('historyDays', 'Backfill default (hari)', Setting::TYPE_INT, 'system', (string) $days);
    }

    // ==== Generic ====

    private function write(string $key, string $label, string $type, string $group, string $value): void
    {
        $model = Setting::find()->where(['key' => $key])->one();
        if ($model === null) {
            $model = new Setting();
            $model->key = $key;
            $model->label = $label;
            $model->type = $type;
            $model->group = $group;
        }
        $model->type = $type;
        $model->group = $group;
        $model->value = $value;
        $model->save(false);
    }
}