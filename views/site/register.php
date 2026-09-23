<?php

/** @var yii\web\View $this */
/** @var app\models\form\RegisterForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Register';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-register">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>Isi form di bawah untuk mendaftar akun baru:</p>

    <div class="row">
        <div class="col-lg-5">
            <?php $form = ActiveForm::begin([
                'id' => 'register-form',
                'fieldConfig' => [
                    'template' => "{label}\n{input}\n{error}",
                ],
            ]) ?>

                <?= $form->field($model, 'username')->textInput(['autofocus' => true]) ?>

                <?= $form->field($model, 'email')->textInput(['type' => 'email']) ?>

                <?= $form->field($model, 'password')->passwordInput() ?>

                <div class="form-group mt-3">
                    <div>
                        <?= Html::submitButton('Daftar', ['class' => 'btn btn-primary', 'name' => 'register-button']) ?>
                    </div>
                </div>

            <?php ActiveForm::end() ?>
        </div>
    </div>
</div>
