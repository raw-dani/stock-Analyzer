<?php

/** @var yii\web\View $this */
/** @var app\models\form\ScanFilterForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

?>

<div class="card mb-3">
    <div class="card-body">
        <?php $form = ActiveForm::begin([
            'method' => 'get',
            'fieldConfig' => ['template' => '{label}<div class="col-sm-10">{input}{error}</div>', 'options' => ['class' => 'row g-2 mb-0']],
        ]) ?>
            <div class="row g-2">
                <div class="col-md-2">
                    <?= $form->field($model, 'symbol')->textInput(['placeholder' => 'AAPL']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'exchange')->dropDownList(
                        ['NASDAQ' => 'NASDAQ', 'NYSE' => 'NYSE', 'NYSEARCA' => 'NYSEARCA', 'AMEX' => 'AMEX'],
                        ['prompt' => 'All Exchange', 'class' => 'form-select', 'id' => 'exchange']
                    ) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'sector')->dropDownList(
                        ['' => 'All'] + app\models\Stock::find()->select('sector')->distinct()->where(['not', ['sector' => null]])->indexBy('sector')->column(),
                        ['prompt' => 'All Sectors', 'class' => 'form-select']
                    ) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'minScore')->textInput(['placeholder' => 'Min score']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'minBuyRatio')->textInput(['placeholder' => '0.5']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'minVolumeGrowth')->textInput(['placeholder' => '0.5']) ?>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-2">
                    <?= $form->field($model, 'signal')->dropDownList(
                        ['' => 'All Signal'] + \app\models\WeeklyAnalysis::SIGNALS,
                        ['prompt' => 'All Signal', 'class' => 'form-select']
                    ) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'mode')->dropDownList(
                        ['weekly' => 'Weekly', 'daily' => 'Daily'],
                        ['prompt' => 'Weekly', 'class' => 'form-select', 'id' => 'mode']
                    ) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'minMarketCap')->textInput(['placeholder' => 'Min $ (cth 1M=1000000)']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'maxMarketCap')->textInput(['placeholder' => 'Max $ (cth 1T=1e12)']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'minPrice')->textInput(['placeholder' => 'Min price']) ?>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <?= Html::submitButton('Filter', ['class' => 'btn btn-primary w-100']) ?>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-2">
                    <?= $form->field($model, 'maxPrice')->textInput(['placeholder' => 'Max price']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'minVolume')->textInput(['placeholder' => 'Min volume']) ?>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <?= Html::a('Reset', ['/scanner/index'], ['class' => 'btn btn-outline-secondary w-100']) ?>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-warning" id="btn-run-scan">
                        <span class="btn-text">Run Scan</span>
                        <span class="scan-progress" style="display:none">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            Scanning...
                        </span>
                    </button>
                </div>
            </div>
        <?php ActiveForm::end() ?>

        <div class="progress mt-3" id="scan-progress-bar" style="display:none; height: 1.5rem;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%">Processing scan...</div>
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
                // Route harus 'scanner/run-scan' (bukan 'run-scan') agar
                // menghasilkan /scanner/run-scan — 'run-scan' memetakan ke
                // RunScanController yang tidak ada → 404.
                this.closest('form').setAttribute('action', '<?= Url::to(['scanner/run-scan']) ?>');
                e.preventDefault();
                this.closest('form').submit();
            });

            // exchange kosong ditampilkan sebagai "All" pada dropdown.
            document.getElementById('exchange')?.addEventListener('change', function () {
                if (this.value === 'All') this.value = '';
            });
        </script>
    </div>
</div>
