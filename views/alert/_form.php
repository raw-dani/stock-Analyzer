<?php

/** @var yii\web\View $this */
/** @var app\models\form\AlertForm $model */

use app\models\Alert;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

?>

<?php $form = ActiveForm::begin(['id' => 'alert-form']) ?>
    <div class="row g-2">
        <div class="col-md-3">
            <?= $form->field($model, 'symbol')->textInput(['placeholder' => 'kosong = semua simbol']) ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'condition_type')->dropDownList(Alert::CONDITIONS, ['class' => 'form-select']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'operator')->dropDownList(Alert::OPERATORS, ['class' => 'form-select']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'threshold')->textInput(['placeholder' => 'mis. 0.7 / 80']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'active')->checkbox(['checked' => $model->active]) ?>
        </div>
    </div>
    <div class="form-text mb-3">
        Threshold buy_ratio/volume_growth dalam desimal (0.7 = 70%). Kondisi dievaluasi terhadap weekly analysis minggu terakhir.
    </div>
    <?= Html::submitButton('Simpan', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Batal', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
<?php ActiveForm::end() ?>
