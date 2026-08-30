<?php

declare(strict_types=1);

namespace app\models\form;

use app\services\SettingsService;
use yii\base\Model;

/**
 * SettingsForm (Modul 14.1) — form penyuntingan pengaturan aplikasi (admin.
 * Provider aktif, threshold scoring signal, channel notifikasi, pengaturan
 * sistem. Disimpan ke tabel {{%setting}} via SettingsService.
 */
class SettingsForm extends Model
{
    public $provider;
    public $channelApp = true;
    public $channelEmail = true;
    public $channelTelegram = true;
    public $alertCooldownHours;
    public $apiRateLimitPerMinute;
    public $historyDays;

    // Threshold scoring (points tetap, hanya 'min' yang bisa diubah)
    public $buyRatio1;
    public $buyRatio2;
    public $buyRatio3;
    public $volumeGrowth1;
    public $volumeGrowth2;
    public $volumeGrowth3;
    public $rvol1;
    public $rvol2;
    public $rvol3;
    public $aboveMa20;
    public $aboveMa50;

    public const PROVIDERS = ['csv' => 'CSV (dev)', 'alpha_vantage' => 'Alpha Vantage', 'polygon' => 'Polygon'];
    public const PROVIDER_POINTS = [
        'buyRatio' => [30, 20, 10],
        'volumeGrowth' => [25, 15, 10],
        'rvol' => [20, 15, 10],
    ];

    public function rules(): array
    {
        return [
            [['provider'], 'in', 'range' => array_keys(self::PROVIDERS)],
            [['channelApp', 'channelEmail', 'channelTelegram'], 'boolean'],
            [['alertCooldownHours', 'apiRateLimitPerMinute', 'historyDays'], 'integer', 'min' => 1],
            [['buyRatio1', 'buyRatio2', 'buyRatio3'], 'number', 'min' => 0, 'max' => 1],
            [['volumeGrowth1', 'volumeGrowth2', 'volumeGrowth3'], 'number', 'min' => 0],
            [['rvol1', 'rvol2', 'rvol3'], 'number', 'min' => 0],
            [['aboveMa20', 'aboveMa50'], 'integer', 'min' => 0, 'max' => 100],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'provider' => 'Market data provider',
            'channelApp' => 'In-app notification',
            'channelEmail' => 'Email',
            'channelTelegram' => 'Telegram',
            'alertCooldownHours' => 'Alert cooldown (jam)',
            'apiRateLimitPerMinute' => 'API rate limit / menit',
            'historyDays' => 'Backfill default (hari)',
            'buyRatio1' => 'Buy ratio tier 1 (30 pt)',
            'buyRatio2' => 'Buy ratio tier 2 (20 pt)',
            'buyRatio3' => 'Buy ratio tier 3 (10 pt)',
            'volumeGrowth1' => 'Volume growth tier 1 (25 pt)',
            'volumeGrowth2' => 'Volume growth tier 2 (15 pt)',
            'volumeGrowth3' => 'Volume growth tier 3 (10 pt)',
            'rvol1' => 'RVOL tier 1 (20 pt)',
            'rvol2' => 'RVOL tier 2 (15 pt)',
            'rvol3' => 'RVOL tier 3 (10 pt)',
            'aboveMa20' => 'Price > MA20 (pt)',
            'aboveMa50' => 'Price > MA50 (pt)',
        ];
    }

    /** Isi field dari nilai saat ini (SettingsService + params default). */
    public function loadCurrent(SettingsService $service): void
    {
        $this->provider = $service->provider();
        $channels = $service->notificationChannels();
        $this->channelApp = $channels['app'];
        $this->channelEmail = $channels['email'];
        $this->channelTelegram = $channels['telegram'];
        $this->alertCooldownHours = $service->alertCooldownHours();
        $this->apiRateLimitPerMinute = $service->apiRateLimitPerMinute();
        $this->historyDays = $service->historyDays();

        $t = $service->signalThresholds();
        $this->buyRatio1 = $t['buyRatio'][0]['min'] ?? null;
        $this->buyRatio2 = $t['buyRatio'][1]['min'] ?? null;
        $this->buyRatio3 = $t['buyRatio'][2]['min'] ?? null;
        $this->volumeGrowth1 = $t['volumeGrowth'][0]['min'] ?? null;
        $this->volumeGrowth2 = $t['volumeGrowth'][1]['min'] ?? null;
        $this->volumeGrowth3 = $t['volumeGrowth'][2]['min'] ?? null;
        $this->rvol1 = $t['rvol'][0]['min'] ?? null;
        $this->rvol2 = $t['rvol'][1]['min'] ?? null;
        $this->rvol3 = $t['rvol'][2]['min'] ?? null;
        $this->aboveMa20 = $t['aboveMa20'] ?? null;
        $this->aboveMa50 = $t['aboveMa50'] ?? null;
    }

    /** Bangun array signalThresholds dari field form. */
    public function buildSignalThresholds(): array
    {
        $tiers = static function (array $mins, array $points): array {
            $out = [];
            foreach ($mins as $i => $min) {
                if ($min === null || $min === '') {
                    continue;
                }
                $out[] = ['min' => (float) $min, 'points' => $points[$i] ?? 0];
            }
            return $out;
        };

        return [
            'buyRatio' => $tiers([$this->buyRatio1, $this->buyRatio2, $this->buyRatio3], self::PROVIDER_POINTS['buyRatio']),
            'volumeGrowth' => $tiers([$this->volumeGrowth1, $this->volumeGrowth2, $this->volumeGrowth3], self::PROVIDER_POINTS['volumeGrowth']),
            'rvol' => $tiers([$this->rvol1, $this->rvol2, $this->rvol3], self::PROVIDER_POINTS['rvol']),
            'aboveMa20' => (int) $this->aboveMa20,
            'aboveMa50' => (int) $this->aboveMa50,
        ];
    }

    /** Simpan semua nilai ke SettingsService. */
    public function saveTo(SettingsService $service): void
    {
        $service->setProvider($this->provider);
        $service->setSignalThresholds($this->buildSignalThresholds());
        $service->setNotificationChannel('app', (bool) $this->channelApp);
        $service->setNotificationChannel('email', (bool) $this->channelEmail);
        $service->setNotificationChannel('telegram', (bool) $this->channelTelegram);
        $service->setAlertCooldownHours((int) $this->alertCooldownHours);
        $service->setApiRateLimitPerMinute((int) $this->apiRateLimitPerMinute);
        $service->setHistoryDays((int) $this->historyDays);
    }
}