<?php

/** @var yii\web\View $this */
/** @var app\models\form\ScanFilterForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$isWeekly = ($model->mode ?? 'weekly') !== 'daily';
$isDaily = !$isWeekly;

// Cek preset yang aktif
$isPresetDayBreakout = ($model->minChangePct == 2.0 && $model->minBuyRatio == 0.65);
$isPresetDaySpike = ($model->minBuyRatio == 0.70 && $model->minVolume == 500000);
$isPresetDayAbsorption = ($model->minBuyRatio == 0.65 && $model->maxPrice == 50);

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
                <span class="small fw-bold text-muted me-1">
                    <?= \app\helpers\SvgHelper::icon('lightning-fill', ['class' => 'text-warning']) ?> Preset Cepat (<?= $isDaily ? 'Day Trading' : 'Swing Weekly' ?>):
                </span>
                
                <?php if ($isDaily): ?>
                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('rocket-takeoff-fill') . ' Day Breakout (+2% & Acc ≥65%)',
                        ['/scanner/index', 'mode' => 'daily', 'minChangePct' => '2.0', 'minBuyRatio' => '0.65'],
                        ['class' => 'btn btn-sm ' . ($isPresetDayBreakout ? 'btn-success shadow-sm' : 'btn-outline-success'), 'title' => 'Momentum kenaikan hari ini dengan dominasi akumulasi buy']
                    ) ?>

                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('fire') . ' High Volume Surge (≥70% Buy)',
                        ['/scanner/index', 'mode' => 'daily', 'minBuyRatio' => '0.70', 'minVolume' => '500000'],
                        ['class' => 'btn btn-sm ' . ($isPresetDaySpike ? 'btn-primary shadow-sm' : 'btn-outline-primary'), 'title' => 'Tekanan beli kuat dengan likuiditas volume tinggi']
                    ) ?>

                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('arrow-down-left-square') . ' Buy On Absorption (<$50)',
                        ['/scanner/index', 'mode' => 'daily', 'minBuyRatio' => '0.65', 'maxPrice' => '50'],
                        ['class' => 'btn btn-sm ' . ($isPresetDayAbsorption ? 'btn-info text-white shadow-sm' : 'btn-outline-info'), 'title' => 'Saham terjangkau dengan akumulasi buy dominan']
                    ) ?>
                <?php else: ?>
                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-warning']) . ' Shakeout Low (Pin Reversal)',
                        ['/candlestick/index', 'preset' => 'shakeout_low'],
                        ['class' => 'btn btn-sm btn-outline-warning text-dark', 'title' => 'Scan saham dengan pola Shakeout Low / Bear Trap Reversal']
                    ) ?>

                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('buildings') . ' Akumulasi Institusi',
                        ['/scanner/index', 'minBuyRatio' => '0.7', 'minScore' => '60', 'mode' => 'weekly'],
                        ['class' => 'btn btn-sm ' . ($isPresetInstAcc ? 'btn-primary shadow-sm' : 'btn-outline-primary'), 'title' => 'Buy Ratio ≥ 70%, Skor ≥ 60']
                    ) ?>

                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('graph-up-arrow') . ' Volume Breakout',
                        ['/scanner/index', 'minVolumeGrowth' => '0.5', 'minScore' => '65', 'mode' => 'weekly'],
                        ['class' => 'btn btn-sm ' . ($isPresetVolBreakout ? 'btn-success shadow-sm' : 'btn-outline-success'), 'title' => 'Vol Growth ≥ 50%, Skor ≥ 65']
                    ) ?>

                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('gem') . ' Strong Buy Signal',
                        ['/scanner/index', 'signal' => 'STRONG_BUY', 'mode' => 'weekly'],
                        ['class' => 'btn btn-sm ' . ($isPresetStrongBuy ? 'btn-info text-white shadow-sm' : 'btn-outline-info'), 'title' => 'Sinyal akumulasi terkuat']
                    ) ?>

                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('fire') . ' Lonjakan RVOL (≥ 2.0x)',
                        ['/scanner/index', 'minRvol' => '2.0', 'minScore' => '50', 'mode' => 'weekly'],
                        ['class' => 'btn btn-sm ' . ($isPresetRvolSurge ? 'btn-danger shadow-sm' : 'btn-outline-danger'), 'title' => 'Volume 2x lipat rata-rata mingguan']
                    ) ?>

                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('shield-check') . ' Large Cap Giants (>$10B)',
                        ['/scanner/index', 'minMarketCap' => '10000000000', 'minBuyRatio' => '0.55', 'mode' => 'weekly'],
                        ['class' => 'btn btn-sm ' . ($isPresetLargeCap ? 'btn-dark shadow-sm' : 'btn-outline-dark'), 'title' => 'Saham berkapitalisasi pasar raksasa']
                    ) ?>

                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('rocket-takeoff') . ' Momentum Saham Murah (<$20)',
                        ['/scanner/index', 'maxPrice' => '20', 'minVolumeGrowth' => '0.3', 'mode' => 'weekly'],
                        ['class' => 'btn btn-sm ' . ($isPresetMomentum ? 'btn-warning text-dark shadow-sm' : 'btn-outline-warning text-dark'), 'title' => 'Harga di bawah $20 dengan pertumbuhan volume']
                    ) ?>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?= Html::a(\app\helpers\SvgHelper::icon('arrow-counterclockwise') . ' Reset Filter', ['/scanner/index', 'mode' => $model->mode], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Filter Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
        <strong class="small text-secondary"><?= \app\helpers\SvgHelper::icon('funnel') ?> Kustomisasi Filter Scanner</strong>
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
                    <?php if ($isDaily): ?>
                        <?= $form->field($model, 'minChangePct')->textInput(['placeholder' => 'Cth: 2.0 (+2%)'])->label('Min Perubahan Hari Ini (%)') ?>
                    <?php else: ?>
                        <?= $form->field($model, 'minVolumeGrowth')->textInput(['placeholder' => 'Cth: 0.5 (50%)'])->label('Min Vol Growth') ?>
                    <?php endif; ?>
                </div>
                <div class="col-md-2 col-6">
                    <?= $form->field($model, 'minRvol')->textInput(['placeholder' => 'Cth: 1.5 atau 2.0'])->label('Min RVOL (Relatif)') ?>
                </div>
                <div class="col-md-3 col-6">
                    <?= $form->field($model, 'minScore')->textInput(['placeholder' => 'Cth: 70'])->label('Min Skor Komposit (0-100)') ?>
                </div>
                <div class="col-md-3 col-12">
                    <?= $form->field($model, 'minVolume')->textInput(['placeholder' => 'Cth: 500,000'])->label('Min Total Volume (Lembar)') ?>
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
                    <?= \app\helpers\SvgHelper::icon('info-circle', ['class' => 'me-1']) ?> Data diurutkan otomatis berdasarkan <strong>Skor Tertinggi</strong> &amp; <strong>Volume Akumulasi</strong>.
                </div>
                <div class="d-flex gap-2">
                    <?= Html::a(\app\helpers\SvgHelper::icon('x-circle') . ' Reset', ['/scanner/index', 'mode' => $model->mode], ['class' => 'btn btn-outline-secondary btn-sm px-3']) ?>
                    <?= Html::submitButton(\app\helpers\SvgHelper::icon('search') . ' Terapkan Filter', ['class' => 'btn btn-primary btn-sm px-4 fw-bold']) ?>
                    <button type="submit" class="btn btn-warning btn-sm px-3" id="btn-run-scan">
                        <span class="btn-text"><?= \app\helpers\SvgHelper::icon('arrow-clockwise') ?> Jalankan Scan Harian</span>
                        <span class="scan-progress" style="display:none">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            Memproses...
                        </span>
                    </button>
                </div>
            </div>
        <?php ActiveForm::end() ?>

        <div class="progress mt-3" id="scan-progress-bar" style="display:none; height: 1.25rem;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark fw-bold" role="progressbar" style="width: 100%">Menganalisis indikator volume, momentum &amp; scoring sinyal...</div>
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
                const form = this.closest('form');
                form.setAttribute('action', '<?= Url::to(['scanner/run-scan']) ?>');
                form.submit();
            });

            document.getElementById('exchange')?.addEventListener('change', function () {
                if (this.value === 'All') this.value = '';
            });
        </script>
    </div>
</div>
