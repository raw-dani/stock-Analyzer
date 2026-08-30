<?php

/** @var yii\web\View $this */
/** @var app\models\form\SignalFilterForm $model */

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
                <div class="col-md-3">
                    <?= $form->field($model, 'symbol')->textInput(['placeholder' => 'NVDA']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'signal')->dropDownList(
                        ['' => 'All Signals'] + app\models\WeeklyAnalysis::SIGNALS,
                        ['class' => 'form-select']
                    ) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'dateFrom')->textInput(['placeholder' => 'YYYY-MM-DD']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'dateTo')->textInput(['placeholder' => 'YYYY-MM-DD']) ?>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <?= Html::submitButton('Filter', ['class' => 'btn btn-primary w-100']) ?>
                </div>
            </div>
        <?php ActiveForm::end() ?>
    </div>
</div>
