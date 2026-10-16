<?php

/** @var yii\web\View $this */
/** @var app\models\form\RegisterForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Daftar Akun Baru - Stock Analyzer';
?>
<div class="site-register py-4">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-success bg-gradient text-white p-4 text-center border-0">
                    <div class="mb-2" style="font-size: 2.2rem;">💼</div>
                    <h3 class="fw-bold mb-1">Buat Akun Baru</h3>
                    <p class="small text-white-50 mb-0">Mulai lacak portofolio dan strategi averaging Anda</p>
                </div>
                <div class="card-body p-4">
                    <?php $form = ActiveForm::begin([
                        'id' => 'register-form',
                        'fieldConfig' => [
                            'template' => "{label}\n{input}\n{error}",
                            'labelOptions' => ['class' => 'form-label fw-semibold small text-muted'],
                            'inputOptions' => ['class' => 'form-control form-control-lg fs-6'],
                            'errorOptions' => ['class' => 'invalid-feedback'],
                        ],
                    ]); ?>

                    <?= $form->field($model, 'username')->textInput([
                        'autofocus' => true,
                        'placeholder' => 'Pilih username unik',
                    ])->label('Username') ?>

                    <?= $form->field($model, 'email')->textInput([
                        'type' => 'email',
                        'placeholder' => 'nama@email.com',
                    ])->label('Alamat Email') ?>

                    <?= $form->field($model, 'password')->passwordInput([
                        'placeholder' => 'Minimal 6 karakter',
                    ])->label('Password') ?>

                    <div class="d-grid mt-4 mb-3">
                        <?= Html::submitButton(\app\helpers\SvgHelper::icon('person-plus-fill', ['class' => 'me-1']) . ' Daftar Sekarang', [
                            'class' => 'btn btn-success btn-lg fw-bold shadow-sm',
                            'name' => 'register-button'
                        ]) ?>
                    </div>

                    <div class="text-center small text-muted">
                        Sudah punya akun? <?= Html::a('Masuk di sini', ['/site/login'], ['class' => 'fw-bold text-decoration-none']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
