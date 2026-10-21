<?php

/** @var yii\web\View $this */
/** @var bool|null $fromCache */
/** @var string|null $timeframe */

use app\services\ScannerCacheService;
use yii\bootstrap5\Html;

$info = ScannerCacheService::getCryptoSyncStatusInfo($timeframe ?? '1h');
$latestBarStr = !empty($info['latest_bar_time']) ? Html::encode($info['latest_bar_time']) : '—';
$latestSync = !empty($info['latest_created_at']) ? Yii::$app->formatter->asRelativeTime($info['latest_created_at']) : '—';
$totalCoins = (int) ($info['total_coins'] ?? 0);
?>

<div class="card border-0 shadow-sm bg-white mb-3" style="border-left: 4px solid #f0b90b !important;">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="text-warning fs-5"><i class="bi bi-currency-bitcoin"></i></span>
                <div>
                    <span class="small fw-bold text-dark">Status Data Binance (24/7):</span>
                    <?php if (!empty($fromCache)): ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success ms-1" style="font-size: 0.72rem;">
                            ✓ Hasil Analisis Tersimpan (Data Terkini)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning ms-1" style="font-size: 0.72rem;">
                            ⚡ Analisis Baru Selesai (Sinkron dengan DB)
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 small text-muted">
                <div>
                    <i class="bi bi-clock-history"></i> Sync Binance:
                    <strong class="text-dark"><?= $latestSync ?></strong>
                    <span class="badge bg-light text-secondary border ms-1" title="Bar Candle Terakhir"><?= $latestBarStr ?> (UTC)</span>
                    <span class="badge bg-light text-dark border ms-1"><?= $totalCoins ?> Koin</span>
                </div>
                <div class="d-none d-md-block border-start ps-3">
                    <span class="text-secondary" title="Sistem tidak akan melakukan kalkulasi ulang jika database belum di-sync data baru dari Binance">
                        <i class="bi bi-lightning-charge-fill text-warning"></i> Fast Cache Enabled
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
