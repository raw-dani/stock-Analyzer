<?php

/** @var yii\web\View $this */
/** @var app\models\form\BacktestForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Jalankan Backtest';
$this->params['breadcrumbs'][] = ['label' => 'Backtesting', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<div class="card"><div class="card-body">
    <?php $form = ActiveForm::begin(['id' => 'backtest-form']) ?>
        <div class="row g-2">
            <div class="col-md-3">
                <?= $form->field($model, 'name')->textInput(['placeholder' => 'kosong = auto']) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'minBuyRatio')->textInput(['placeholder' => '0.7']) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'minRvol')->textInput(['placeholder' => '1.5']) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'minScore')->textInput(['placeholder' => '80']) ?>
            </div>
            <div class="col-md-1">
                <?= $form->field($model, 'holdingDays')->textInput() ?>
            </div>
            <div class="col-md-1">
                <?= $form->field($model, 'startDate')->textInput(['placeholder' => 'YYYY-MM-DD']) ?>
            </div>
            <div class="col-md-1">
                <?= $form->field($model, 'endDate')->textInput(['placeholder' => 'YYYY-MM-DD']) ?>
            </div>
        </div>
        <div class="form-text mb-3">
            Entry: close hari bursa pertama ≥ week_start milik weekly_analysis yang lolos kriteria.
            Exit: close N hari bursa setelahnya. Kosongkan kriteria = semua sinyal.
        </div>
        <?= Html::submitButton('▶ Jalankan', ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Batal', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
    <?php ActiveForm::end() ?>
</div></div>
