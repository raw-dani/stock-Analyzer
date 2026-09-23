<?php

/** @var yii\web\View $this */
/** @var app\models\Watchlist $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = $model->isNewRecord ? 'Buat Watchlist' : 'Edit Watchlist';
$this->params['breadcrumbs'][] = ['label' => 'Watchlist', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="watchlist-form">
    <h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin([
        'id' => 'watchlist-form',
    ]); ?>

        <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

        <?= $form->field($model, 'description')->textarea(['rows' => 3, 'maxlength' => true]) ?>

        <div class="form-group mt-3">
            <?= Html::submitButton($model->isNewRecord ? 'Buat' : 'Simpan', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Batal', ['index'], ['class' => 'btn btn-link']) ?>
        </div>

    <?php ActiveForm::end() ?>
</div>
