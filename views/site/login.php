<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var app\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Login - Stock Analyzer';
?>
<div class="site-login py-4">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-primary bg-gradient text-white p-4 text-center border-0">
                    <div class="mb-2" style="font-size: 2.2rem;">📈</div>
                    <h3 class="fw-bold mb-1">Selamat Datang</h3>
                    <p class="small text-white-50 mb-0">Masuk untuk mengelola portofolio & analisis saham Anda</p>
                </div>
                <div class="card-body p-4">
                    <?php $form = ActiveForm::begin([
                        'id' => 'login-form',
                        'fieldConfig' => [
                            'template' => "{label}\n{input}\n{error}",
                            'labelOptions' => ['class' => 'form-label fw-semibold small text-muted'],
                            'inputOptions' => ['class' => 'form-control form-control-lg fs-6'],
                            'errorOptions' => ['class' => 'invalid-feedback'],
                        ],
                    ]); ?>

                    <?= $form->field($model, 'username')->textInput([
                        'autofocus' => true,
                        'placeholder' => 'Username',
                    ])->label('Username') ?>

                    <?= $form->field($model, 'password')->passwordInput([
                        'placeholder' => 'Password',
                    ])->label('Password') ?>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <?= $form->field($model, 'rememberMe')->checkbox([
                            'class' => 'form-check-input',
                            'labelOptions' => ['class' => 'form-check-label small text-muted'],
                        ])->label('Ingat saya') ?>
                    </div>

                    <div class="d-grid mb-3">
                        <?= Html::submitButton('<i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang', [
                            'class' => 'btn btn-primary btn-lg fw-bold shadow-sm',
                            'name' => 'login-button'
                        ]) ?>
                    </div>

                    <div class="text-center small text-muted">
                        Belum punya akun? <?= Html::a('Daftar di sini', ['/site/register'], ['class' => 'fw-bold text-decoration-none']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>

                    <hr class="my-3">

                    <div class="p-3 bg-light rounded-3 small border">
                        <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle me-1 text-primary"></i> Akun Bawaan (Demo):</div>
                        <ul class="list-unstyled mb-0 text-muted" style="font-size: 0.85rem;">
                            <li>• <strong>Admin</strong>: username <code>admin</code> / password <code>admin</code> (Akses Penuh + Setting)</li>
                            <li>• <strong>User</strong>: username <code>demo</code> / password <code>demo</code> (Portofolio Pribadi)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
