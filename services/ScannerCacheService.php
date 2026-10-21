<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use Yii;

/**
 * Service untuk memantau versi/status data pasar di database
 * dan mengelola caching scanner berbasis versi data (Smart Data Version Caching).
 *
 * Mencegah analisis berulang yang berat jika data di database belum diperbarui (masih sama).
 */
final class ScannerCacheService
{
    /** TTL cache ketika data belum berubah (misal 24 jam / 86400 detik). */
    public const DEFAULT_TTL = 86400;

    /**
     * Dapatkan signature/versi unik dari data pasar di database saat ini.
     * Menggabungkan timestamp update terakhir dan tanggal/waktu maksimum dari Daily & Intraday.
     *
     * @param int|null $timeframe Timeframe scanner (1 = 1H, 2 = 2H, 4 = 4H, 24 = 1D)
     * @return string Hash versi data pasar (e.g. 'v1_daily1792116608_intra1792144269')
     */
    public static function getDataVersion(?int $timeframe = null): string
    {
        $cache = Yii::$app->cache;
        $versionKey = 'market_data_version_' . ($timeframe ?? 'all');

        $version = $cache ? $cache->get($versionKey) : false;
        if ($version !== false && is_string($version)) {
            return $version;
        }

        $dailyMaxUpdate = (int) (DailyPrice::find()->max('updated_at') ?: 0);
        $dailyMaxDate = (string) (DailyPrice::find()->max('date') ?: '');

        // Jika timeframe 1D, fokus pada daily
        if ($timeframe === 24) {
            $version = 'd_' . $dailyMaxUpdate . '_' . $dailyMaxDate;
        } else {
            // Untuk intraday atau umum, perhitungkan IntradayPrice
            $intraMaxUpdate = (int) (IntradayPrice::find()->max('updated_at') ?: 0);
            $intraMaxDt = (string) (IntradayPrice::find()->max('datetime') ?: '');
            $version = 'm_' . $dailyMaxUpdate . '_' . $dailyMaxDate . '_i_' . $intraMaxUpdate . '_' . $intraMaxDt;
        }

        // Cache versi selama 60 detik agar tidak query max() berulang-ulang pada request yang bersamaan
        if ($cache) {
            $cache->set($versionKey, $version, 60);
        }

        return $version;
    }

    /**
     * Dapatkan metadata status sinkronisasi data pasar untuk ditampilkan di UI scanner.
     * Memberitahu user apakah hasil analisis berasal dari data terbaru hasil sync atau cache data lama.
     */
    public static function getSyncStatusInfo(): array
    {
        $cache = Yii::$app->cache;
        $infoKey = 'market_sync_status_info';
        $info = $cache ? $cache->get($infoKey) : false;

        if ($info === false || !is_array($info)) {
            $dailyMaxUpdate = (int) (DailyPrice::find()->max('updated_at') ?: 0);
            $dailyMaxDate = (string) (DailyPrice::find()->max('date') ?: '');
            $intraMaxUpdate = (int) (IntradayPrice::find()->max('updated_at') ?: 0);
            $intraMaxDt = (string) (IntradayPrice::find()->max('datetime') ?: '');

            $info = [
                'daily_updated_at' => $dailyMaxUpdate,
                'daily_date' => $dailyMaxDate,
                'intraday_updated_at' => $intraMaxUpdate,
                'intraday_datetime' => $intraMaxDt,
            ];

            if ($cache) {
                $cache->set($infoKey, $info, 60);
            }
        }

        return $info;
    }

    /**
     * Buat cache key yang terikat dengan versi data pasar saat ini.
     * Jika data pasar belum sync/berubah, cache key akan tetap sama persis sehingga cache HIT (load instan).
     * Saat admin/cron melakukan sync data baru, signature versi otomatis berubah dan scanner menganalisis ulang data baru.
     *
     * @param string $prefix Nama modul / scanner (e.g. 'confluence', 'bollinger', 'ma')
     * @param array $params Parameter scanner
     * @param int|null $timeframe
     * @return string
     */
    public static function buildCacheKey(string $prefix, array $params, ?int $timeframe = null): string
    {
        $version = self::getDataVersion($timeframe);
        return $prefix . '_' . $version . '_' . md5(json_encode($params));
    }

    /**
     * Bersihkan cache versi data pasar saham (dipanggil setelah sinkronisasi data baru selesai).
     */
    public static function invalidateVersions(): void
    {
        $cache = Yii::$app->cache;
        if ($cache) {
            $cache->delete('market_data_version_all');
            $cache->delete('market_data_version_24');
            $cache->delete('market_data_version_4');
            $cache->delete('market_data_version_2');
            $cache->delete('market_data_version_1');
            $cache->delete('market_sync_status_info');
        }
    }

    /**
     * Dapatkan signature/versi unik dari data Crypto di database saat ini.
     * Menggabungkan timestamp update/bar_time terakhir dari tabel crypto_price dan crypto_analysis.
     *
     * @param string|null $timeframe Timeframe crypto ('15m', '1h', '2h', '4h', '1d')
     * @return string Hash versi data crypto
     */
    public static function getCryptoDataVersion(?string $timeframe = null): string
    {
        $cache = Yii::$app->cache;
        $versionKey = 'crypto_data_version_' . ($timeframe ?? 'all');

        $version = $cache ? $cache->get($versionKey) : false;
        if ($version !== false && is_string($version)) {
            return $version;
        }

        $priceQuery = \app\models\CryptoPrice::find();
        $analysisQuery = \app\models\CryptoAnalysis::find();

        if (!empty($timeframe)) {
            $priceQuery->where(['timeframe' => $timeframe]);
            $analysisQuery->where(['timeframe' => $timeframe]);
        }

        $maxBarTime = (string) ($priceQuery->max('bar_time') ?: '');
        $maxPriceCreated = (int) ($priceQuery->max('created_at') ?: 0);
        $maxAnalysisUpdate = (int) ($analysisQuery->max('updated_at') ?: 0);

        $version = 'c_' . ($timeframe ?? 'all') . '_' . $maxPriceCreated . '_' . $maxAnalysisUpdate . '_' . md5($maxBarTime);

        // Cache versi selama 60 detik
        if ($cache) {
            $cache->set($versionKey, $version, 60);
        }

        return $version;
    }

    /**
     * Dapatkan info status sinkronisasi data Crypto untuk banner UI.
     */
    public static function getCryptoSyncStatusInfo(?string $timeframe = null): array
    {
        $cache = Yii::$app->cache;
        $infoKey = 'crypto_sync_status_info_' . ($timeframe ?? 'all');
        $info = $cache ? $cache->get($infoKey) : false;

        if ($info === false || !is_array($info)) {
            $priceQuery = \app\models\CryptoPrice::find();
            if (!empty($timeframe)) {
                $priceQuery->where(['timeframe' => $timeframe]);
            }

            $maxBarTime = (string) ($priceQuery->max('bar_time') ?: '');
            $maxPriceCreated = (int) ($priceQuery->max('created_at') ?: 0);
            $totalCoins = (int) \app\models\CryptoAsset::find()->where(['is_active' => true])->count();

            $info = [
                'latest_bar_time' => $maxBarTime,
                'latest_created_at' => $maxPriceCreated,
                'total_coins' => $totalCoins,
            ];

            if ($cache) {
                $cache->set($infoKey, $info, 60);
            }
        }

        return $info;
    }

    /**
     * Buat cache key untuk modul Crypto berbasis versi data terkini.
     */
    public static function buildCryptoCacheKey(string $prefix, array $params, ?string $timeframe = null): string
    {
        $version = self::getCryptoDataVersion($timeframe);
        return 'crypto_' . $prefix . '_' . $version . '_' . md5(json_encode($params));
    }

    /**
     * Bersihkan cache versi data crypto saat sinkronisasi Binance selesai.
     */
    public static function invalidateCryptoVersions(): void
    {
        $cache = Yii::$app->cache;
        if ($cache) {
            $cache->delete('crypto_data_version_all');
            foreach (['15m', '1h', '2h', '4h', '1d'] as $tf) {
                $cache->delete('crypto_data_version_' . $tf);
                $cache->delete('crypto_sync_status_info_' . $tf);
            }
            $cache->delete('crypto_sync_status_info_all');
        }
    }
}

