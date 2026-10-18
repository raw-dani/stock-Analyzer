<?php

declare(strict_types=1);

namespace app\commands;

use app\models\CryptoAsset;
use app\services\crypto\CryptoAnalyzerService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Command CLI untuk mengelola data & analisis Crypto (terpisah dari modul saham).
 */
class CryptoController extends Controller
{
    public string $timeframe = '1h';
    public int $limit = 100;

    private CryptoAnalyzerService $service;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['timeframe', 'limit']);
    }

    public function init(): void

    {
        parent::init();
        $this->service = new CryptoAnalyzerService();
        $this->service->init();
    }

    /**
     * Daftarkan / refresh daftar Top 28 Koin Crypto likuiditas tinggi
     * Contoh: php yii crypto/refresh-list
     */
    public function actionRefreshList(): int
    {
        $this->stdout("Memperbarui daftar koin crypto dari Binance...\n", Console::FG_YELLOW);
        $count = $this->service->refreshCoinList();
        $this->stdout("Berhasil mendaftarkan/memperbarui {$count} koin crypto aktif.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Sinkronisasi data candlestick & volume untuk satu atau semua koin
     * Contoh: php yii crypto/sync BTCUSDT --timeframe=1h --limit=100
     */
    public function actionSync(?string $symbol = null): int
    {
        $timeframe = $this->timeframe;
        $limit = $this->limit;

        if ($symbol !== null) {
            $symbol = strtoupper($symbol);
            $asset = CryptoAsset::findOne(['symbol' => $symbol]);
            if ($asset === null) {
                $this->stderr("Koin {$symbol} tidak ditemukan di database.\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }

            $this->stdout("Mengambil data klines {$symbol} ({$timeframe})...\n", Console::FG_YELLOW);
            $saved = $this->service->syncCoinBars($asset, $timeframe, $limit);
            $this->stdout("Tersimpan {$saved} bar untuk {$symbol}.\n", Console::FG_GREEN);

            $this->service->analyze($asset, $timeframe);
            $this->stdout("Analisis teknikal {$symbol} ({$timeframe}) selesai diperbarui.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        return $this->actionSyncAll();
    }

    /**
     * Sinkronisasi dan analisis seluruh koin crypto aktif
     * Contoh: php yii crypto/sync-all --timeframe=1h
     */
    public function actionSyncAll(): int
    {
        $timeframe = $this->timeframe;
        $limit = $this->limit;

        $this->stdout("Memulai sinkronisasi semua koin crypto (timeframe: {$timeframe})...\n", Console::FG_YELLOW);

        $res = $this->service->syncAndAnalyzeAll($timeframe, $limit, function (string $symbol, int $bars) {
            $this->stdout("  -> {$symbol}: {$bars} bar disinkronisasi & dianalisis.\n", Console::FG_GREY);
        });

        $this->stdout("\nSelesai! Total koin: {$res['total_coins']}, Berhasil disinkron: {$res['synced']}, Dianalisis: {$res['analyzed']}.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Jalankan analisis teknikal & volume pressure tanpa download ulang
     * Contoh: php yii crypto/analyze BTCUSDT --timeframe=1h
     */
    public function actionAnalyze(?string $symbol = null): int
    {
        $timeframe = $this->timeframe;

        if ($symbol !== null) {
            $asset = CryptoAsset::findOne(['symbol' => strtoupper($symbol)]);
            if ($asset === null) {
                $this->stderr("Koin {$symbol} tidak ditemukan.\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }
            $analysis = $this->service->analyze($asset, $timeframe);
            if ($analysis !== null) {
                $this->stdout("Analisis {$symbol} ({$timeframe}): Score={$analysis->score}, Signal={$analysis->signal}, BuyRatio={$analysis->buy_ratio}%, RVOL={$analysis->rvol}x\n", Console::FG_GREEN);
            }
            return ExitCode::OK;
        }

        $coins = CryptoAsset::find()->where(['is_active' => true])->all();
        $this->stdout("Menganalisis " . count($coins) . " koin crypto ({$timeframe})...\n", Console::FG_YELLOW);
        foreach ($coins as $coin) {
            $this->service->analyze($coin, $timeframe);
        }
        $this->stdout("Analisis selesai untuk seluruh koin.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
}
