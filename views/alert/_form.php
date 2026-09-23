<?php

/** @var yii\web\View $this */
/** @var app\models\form\AlertForm $model */

use app\models\Alert;
use app\models\form\AlertForm;
use app\services\RsiDoubleBottomService;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\web\JsExpression;

$isRsiDoubleBottom = $model->condition_type === Alert::CONDITION_RSI_DOUBLE_BOTTOM;
$rsiDefaults = AlertForm::getRsiDoubleBottomDefaultParams();
$timeframes = RsiDoubleBottomService::getTimeframes();

$this->registerJs(<<<JS
    const conditionSelect = document.getElementById('alertform-condition_type');
    const rsiFields = document.getElementById('rsi-double-bottom-fields');

    function toggleRsiFields() {
        if (conditionSelect.value === 'rsi_double_bottom') {
            rsiFields.style.display = 'block';
        } else {
            rsiFields.style.display = 'none';
        }
    }

    conditionSelect.addEventListener('change', toggleRsiFields);
    toggleRsiFields(); // initial
JS
);

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
            <?= $form->field($model, 'threshold')->textInput(['placeholder' => 'mis. 0.7 / 80 / 70']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'active')->checkbox(['checked' => $model->active]) ?>
        </div>
    </div>
    <div class="form-text mb-3">
        Threshold buy_ratio/volume_growth dalam desimal (0.7 = 70%). Kondisi dievaluasi terhadap weekly analysis minggu terakhir.
    </div>

    <!-- RSI Double Bottom specific params -->
    <div id="rsi-double-bottom-fields" class="card card-body mb-3" style="display: <?= $isRsiDoubleBottom ? 'block' : 'none' ?>;">
        <h6 class="mb-3">RSI Double Bottom Parameters</h6>
        <div class="row g-2">
            <div class="col-md-2">
                <?= $form->field($model, 'threshold_data[timeframe]')->dropDownList($timeframes, [
                    'class' => 'form-select',
                    'prompt' => 'Timeframe',
                    'options' => [$model->threshold_data['timeframe'] ?? $rsiDefaults['timeframe'] => ['Selected' => true]],
                ]) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'threshold_data[lookback]')->textInput([
                    'type' => 'number',
                    'min' => 20,
                    'max' => 300,
                    'placeholder' => 'Lookback',
                    'value' => $model->threshold_data['lookback'] ?? $rsiDefaults['lookback'],
                ]) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'threshold_data[tolerance]')->textInput([
                    'type' => 'number',
                    'step' => 0.5,
                    'min' => 0.5,
                    'max' => 10,
                    'placeholder' => 'Tolerance',
                    'value' => $model->threshold_data['tolerance'] ?? $rsiDefaults['tolerance'],
                ]) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'threshold_data[rsiPeriod]')->textInput([
                    'type' => 'number',
                    'min' => 2,
                    'max' => 50,
                    'placeholder' => 'RSI Period',
                    'value' => $model->threshold_data['rsiPeriod'] ?? $rsiDefaults['rsiPeriod'],
                ]) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'threshold_data[maxRsi]')->textInput([
                    'type' => 'number',
                    'step' => 5,
                    'min' => 20,
                    'max' => 70,
                    'placeholder' => 'Max RSI',
                    'value' => $model->threshold_data['maxRsi'] ?? $rsiDefaults['maxRsi'],
                ]) ?>
            </div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-2">
                <?= $form->field($model, 'threshold_data[minSeparation]')->textInput([
                    'type' => 'number',
                    'min' => 1,
                    'placeholder' => 'Min Sep',
                    'value' => $model->threshold_data['minSeparation'] ?? $rsiDefaults['minSeparation'],
                ]) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'threshold_data[maxSeparation]')->textInput([
                    'type' => 'number',
                    'min' => 1,
                    'placeholder' => 'Max Sep',
                    'value' => $model->threshold_data['maxSeparation'] ?? $rsiDefaults['maxSeparation'],
                ]) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'threshold_data[necklineMin]')->textInput([
                    'type' => 'number',
                    'step' => 0.5,
                    'min' => 0,
                    'placeholder' => 'Neckline Min',
                    'value' => $model->threshold_data['necklineMin'] ?? $rsiDefaults['necklineMin'],
                ]) ?>
            </div>
        </div>
        <div class="form-text mt-2">
            <small>Operator untuk RSI Double Bottom:
                <code>breakout</code> (RSI break neckline),
                <code>confidence</code> (confidence >= threshold),
                <code>divergence</code> (bullish divergence),
                <code>found</code> / <code>=</code> / <code>>=</code> (pola ditemukan)
            </small>
        </div>
    </div>

    <?= Html::submitButton('Simpan', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Batal', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
<?php ActiveForm::end() ?>
