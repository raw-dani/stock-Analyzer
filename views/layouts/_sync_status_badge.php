<?php

/** @var yii\web\View $this */
/** @var bool|null $fromCache */
/** @var int|null $timeframe */

use app\services\ScannerCacheService;
use yii\bootstrap5\Html;

$info = ScannerCacheService::getSyncStatusInfo();
$isDaily = ($timeframe === 24 || $timeframe === null);

$lastSyncTime = $isDaily 
    ? (!empty($info['daily_updated_at']) ? Yii::$app->formatter->asRelativeTime($info['daily_updated_at']) : '—')
    : (!empty($info['intraday_updated_at']) ? Yii::$app->formatter->asRelativeTime($info['intraday_updated_at']) : (!empty($info['intraday_datetime']) ? Html::encode($info['intraday_datetime']) : '—'));

$lastDateStr = $isDaily
    ? (!empty($info['daily_date']) ? Html::encode($info['daily_date']) : '—')
    : (!empty($info['intraday_datetime']) ? Html::encode($info['intraday_datetime']) : '—');
?>

<div class="card border-0 shadow-sm bg-white mb-3" style="border-left: 3px solid var(--brand-primary) !important;">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="text-primary fs-5"><?= \app\helpers\SvgHelper::icon('shield-check') ?></span>
                <div>
                    <span class="small fw-bold text-dark">Status Data Pasar:</span>
                    <?php if (!empty($fromCache)): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.72rem;">
                            ✓ Cache Cepat Aktif (Data Terkini)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 0.72rem;">
                            ⚡ Analisis Terkini
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 small text-muted">
                <div>
                    <?= \app\helpers\SvgHelper::icon('clock-history') ?> Terakhir Diperbarui:
                    <strong class="text-dark"><?= $lastSyncTime ?></strong>
                    <span class="badge bg-light text-secondary border ms-1"><?= $lastDateStr ?></span>
                </div>
                <div class="d-none d-md-block border-start ps-3">
                    <span class="text-secondary" title="Sistem menggunakan indexing pintar agar respon tabel instan">
                        <?= \app\helpers\SvgHelper::icon('lightning-charge', ['class' => 'text-warning']) ?> Turbo Index
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
