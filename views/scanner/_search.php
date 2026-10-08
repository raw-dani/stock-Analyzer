<?php

/** @var yii\web\View $this */
/** @var app\models\form\ScanFilterForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$isWeekly = ($model->mode ?? 'weekly') !== 'daily';

// Cek preset yang aktif
$isPresetInstAcc = ($model->minBuyRatio == 0.7 && $model->minScore == 60);
$isPresetVolBreakout = ($model->minVolumeGrowth == 0.5 && $model->minScore == 65);
$isPresetStrongBuy = ($model->signal === 'STRONG_BUY');
$isPresetRvolSurge = ($model->minRvol == 2.0 && $model->minScore == 50);
$isPresetLargeCap = ($model->minMarketCap == 10000000000 && $model->minBuyRatio == 0.55);
$isPresetMomentum = ($model->maxPrice == 20 && $model->minVolumeGrowth == 0.3);
?>

<!-- Preset Strategy Buttons (1-Klik) -->
<div class="card border-0 shadow-sm mb-3 bg-light">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="small fw-bold text-muted me-1"><i class="bi bi-lightning-fill text-warning"></i> Preset Strategi Cepat:</span>
                
                <?= Html::a(
                    '<i class="bi bi-buildings"></i> Akumulasi Institusi',
                    ['/scanner/index', 'minBuyRatio' => '0.7', 'minScore' => '60', 'mode' => $model->mode],
                    ['class' => 'btn btn-sm ' . ($isPresetInstAcc ? 'btn-primary shadow-sm' : 'btn-outline-primary'), 'title' => 'Buy Ratio ≥ 70%, Skor ≥ 60']
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-graph-up-arrow"></i> Volume Breakout',
                    ['/scanner/index', 'minVolumeGrowth' => '0.5', 'minScore' => '65', 'mode' => $model->mode],
                    ['class' => 'btn btn-sm ' . ($isPresetVolBreakout ? 'btn-success shadow-sm' : 'btn-outline-success'), 'title' => 'Vol Growth ≥ 50%, Skor ≥ 65']
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-gem"></i> Strong Buy Signal',
                    ['/scanner/index', 'signal' => 'STRONG_BUY', 'mode' => $model->mode],
                    ['class' => 'btn btn-sm ' . ($isPresetStrongBuy ? 'btn-info text-white shadow-sm' : 'btn-outline-info'), 'title' => 'Sinyal akumulasi terkuat']
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-fire"></i> Lonjakan RVOL (≥ 2.0x)',
                    ['/scanner/index', 'minRvol' => '2.0', 'minScore' => '50', 'mode' => $model->mode],
                    ['class' => 'btn btn-sm ' . ($isPresetRvolSurge ? 'btn-danger shadow-sm' : 'btn-outline-danger'), 'title' => 'Volume 2x lipat rata-rata mingguan']
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-shield-check"></i> Large Cap Giants (>$10B)',
                    ['/scanner/index', 'minMarketCap' => '10000000000', 'minBuyRatio' => '0.55', 'mode' => $model->mode],
                    ['class' => 'btn btn-sm ' . ($isPresetLargeCap ? 'btn-dark shadow-sm' : 'btn-outline-dark'), 'title' => 'Saham berkapitalisasi pasar raksasa']
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-rocket-takeoff"></i> Momentum Saham Murah (<$20)',
                    ['/scanner/index', 'maxPrice' => '20', 'minVolumeGrowth' => '0.3', 'mode' => $model->mode],
                    ['class' => 'btn btn-sm ' . ($isPresetMomentum ? 'btn-warning text-dark shadow-sm' : 'btn-outline-warning text-dark'), 'title' => 'Harga di bawah $20 dengan pertumbuhan volume']
                ) ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?= Html::a('<i class="bi bi-arrow-counterclockwise"></i> Reset Filter', ['/scanner/index', 'mode' => $model->mode], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Filter Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
        <strong class="small text-secondary"><i class="bi bi-funnel"></i> Kustomisasi Filter Scanner</strong>
        <span class="badge bg-light text-dark border">Mode: <?= strtoupper($model->mode ?? 'weekly') ?></span>
    </div>
    <div class="card-body p-3">
        <?php $form = ActiveForm::begin([
            'action' => ['/scanner/index'],
            'method' => 'get',
            'fieldConfig' => [
                'template' => '{label}{input}{error}',
                'options' => ['class' => 'mb-2'],
                'labelOptions' => ['class' => 'form-label small fw-bold text-muted mb-1'],
                'inputOptions' => ['class' => 'form-control form-control-sm'],
            ],
        ]) ?>
            <!-- Row 1: Identitas & Sektor -->
            <div class="row g-2">
                <div class="col-md-2 col-6">
                    <?= $form->field($model, 'symbol')->textInput(['placeholder' => 'Cth: NVDA, AAPL']) ?>
                </div>
                <div class="col-md-2 col-6">
                    <?= $form->field($model, 'mode')->dropDownList(
                        ['weekly' => 'Weekly (Mingguan)', 'daily' => 'Daily (Harian)'],
                        ['class' => 'form-select form-select-sm', 'id' => 'mode', 'onchange' => 'this.form.submit()']
                    ) ?>
                </div>
                <div class="col-md-2 col-6">
                    <?= $form->field($model, 'exchange')->dropDownList(
                        ['NASDAQ' => 'NASDAQ', 'NYSE' => 'NYSE', 'NYSEARCA' => 'NYSEARCA', 'AMEX' => 'AMEX'],
                        ['prompt' => 'Semua Bursa', 'class' => 'form-select form-select-sm', 'id' => 'exchange']
                    ) ?>
                </div>
                <div class="col-md-3 col-6">
                    <?= $form->field($model, 'sector')->dropDownList(
                        ['' => 'Semua Sektor'] + app\models\Stock::find()->select('sector')->distinct()->where(['not', ['sector' => null]])->indexBy('sector')->column(),
                        ['class' => 'form-select form-select-sm']
                    ) ?>
                </div>
                <div class="col-md-3 col-12">
                    <?= $form->field($model, 'signal')->dropDownList(
                        ['' => 'Semua Sinyal'] + \app\models\WeeklyAnalysis::SIGNALS,
                        ['class' => 'form-select form-select-sm']
                    ) ?>
                </div>
            </div>

            <!-- Row 2: Metrik Volume & Skor -->
            <div class="row g-2 mt-1">
                <div class="col-md-2 col-6">
                    <?= $form->field($model, 'minBuyRatio')->textInput(['placeholder' => 'Cth: 0.65 atau 65%'])->label('Min Buy Ratio (0.0 - 1.0)') ?>
                </div>
                <div class="col-md-2 col-6">
                    <?= $form->field($model, 'minVolumeGrowth')->textInput(['placeholder' => 'Cth: 0.5 (50%)'])->label('Min Vol Growth') ?>
                </div>
                <div class="col-md-2 col-6">
                    <?= $form->field($model, 'minRvol')->textInput(['placeholder' => 'Cth: 1.5 atau 2.0'])->label('Min RVOL (Relatif)') ?>
                </div>
                <div class="col-md-3 col-6">
                    <?= $form->field($model, 'minScore')->textInput(['placeholder' => 'Cth: 70'])->label('Min Skor Komposit (0-100)') ?>
                </div>
                <div class="col-md-3 col-12">
                    <?= $form->field($model, 'minVolume')->textInput(['placeholder' => 'Cth: 1,000,000'])->label('Min Total Volume (Lembar)') ?>
                </div>
            </div>

            <!-- Row 3: Rentang Harga & Market Cap -->
            <div class="row g-2 mt-1">
                <div class="col-md-2 col-6">
                    <?= $form->field($model, 'minPrice')->textInput(['placeholder' => 'Min $'])->label('Harga Minimal ($)') ?>
                </div>
                <div class="col-md-2 col-6">
                    <?= $form->field($model, 'maxPrice')->textInput(['placeholder' => 'Maks $'])->label('Harga Maksimal ($)') ?>
                </div>
                <div class="col-md-3 col-6">
                    <?= $form->field($model, 'minMarketCap')->textInput(['placeholder' => 'Cth: 1000000000 (1B)'])->label('Min Market Cap ($)') ?>
                </div>
                <div class="col-md-3 col-6">
                    <?= $form->field($model, 'maxMarketCap')->textInput(['placeholder' => 'Cth: 50000000000 (50B)'])->label('Maks Market Cap ($)') ?>
                </div>
                <div class="col-md-2 col-12">
                    <?= $form->field($model, 'limit')->dropDownList(
                        [25 => '25 Baris', 50 => '50 Baris', 100 => '100 Baris', 200 => '200 Baris'],
                        ['class' => 'form-select form-select-sm']
                    )->label('Baris per Halaman') ?>
                </div>
            </div>

            <!-- Tombol Aksi -->
            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                <div class="small text-muted">
                    <i class="bi bi-info-circle me-1"></i> Data diurutkan otomatis berdasarkan <strong>Skor Tertinggi</strong> &amp; <strong>Volume Akumulasi</strong>.
                </div>
                <div class="d-flex gap-2">
                    <?= Html::a('<i class="bi bi-x-circle"></i> Reset', ['/scanner/index', 'mode' => $model->mode], ['class' => 'btn btn-outline-secondary btn-sm px-3']) ?>
                    <?= Html::submitButton('<i class="bi bi-search"></i> Terapkan Filter', ['class' => 'btn btn-primary btn-sm px-4 fw-bold']) ?>
                    <button type="submit" class="btn btn-warning btn-sm px-3" id="btn-run-scan">
                        <span class="btn-text"><i class="bi bi-arrow-clockwise"></i> Jalankan Scan Harian</span>
                        <span class="scan-progress" style="display:none">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            Memproses...
                        </span>
                    </button>
                </div>
            </div>
        <?php ActiveForm::end() ?>

        <div class="progress mt-3" id="scan-progress-bar" style="display:none; height: 1.25rem;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark fw-bold" role="progressbar" style="width: 100%">Sinkronisasi data pasar terbaru &amp; analisis ulang...</div>
        </div>

        <script>
            document.getElementById('btn-run-scan')?.addEventListener('click', function (e) {
                const bar = document.getElementById('scan-progress-bar');
                const btn = document.getElementById('btn-run-scan');
                if (bar) bar.style.display = 'block';
                if (btn) {
                    btn.querySelector('.btn-text').style.display = 'none';
                    btn.querySelector('.scan-progress').style.display = 'inline';
                    btn.disabled = true;
                }
                this.closest('form').setAttribute('action', '<?= Url::to(['scanner/run-scan']) ?>');
                e.preventDefault();
                this.closest('form').submit();
            });

            document.getElementById('exchange')?.addEventListener('change', function () {
                if (this.value === 'All') this.value = '';
            });
        </script>
    </div>
</div>
