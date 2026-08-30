<?php

/** @var yii\web\View $this */
/** @var app\models\form\ScanFilterForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

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
                        [['' => 'All'], ['NASDAQ'], ['NYSE'], ['NYSEARCA']],
                        ['prompt' => 'All Exchange', 'class' => 'form-select', 'id' => 'exchange']
                    ) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'signal')->dropDownList(
                        ['', 'All'] + \app\models\WeeklyAnalysis::SIGNALS,
                        ['prompt' => 'All Signals', 'class' => 'form-select']
                    ) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'minScore')->textInput(['placeholder' => 'Min score']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'minBuyRatio')->textInput(['placeholder' => '0.5']) ?>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <?= Html::submitButton('Filter', ['class' => 'btn btn-primary w-100']) ?>
                </div>
            </div>
        <?php ActiveForm::end() ?>
    </div>
</div>

<script>
    // dropdown "All Exchange" → value kosong agar filter tidak ikut
    document.getElementById('exchange').addEventListener('change', function () {
        if (this.value === 'All') this.value = '';
    });
</script>
