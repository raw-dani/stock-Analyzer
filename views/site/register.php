<?php

/** @var yii\web\View $this */
/** @var app\models\form\RegisterForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Daftar Akun Baru - Stock Analyzer Pro';
?>

<div class="site-register py-5">
    <div class="row justify-content-center align-items-center min-vh-75">
        <div class="col-12 col-md-10 col-lg-9 col-xl-8">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="row g-0">
                    <!-- Kolom Info Kiri -->
                    <div class="col-md-5 bg-dark text-white p-4 p-lg-5 d-flex flex-column justify-content-between position-relative overflow-hidden" 
                         style="background: linear-gradient(145deg, #072216 0%, #0d3d29 60%, #155724 100%);">
                        
                        <!-- Dekorasi background glow -->
                        <div class="position-absolute rounded-circle" 
                             style="width: 250px; height: 250px; background: radial-gradient(circle, rgba(25,135,84,0.3) 0%, rgba(0,0,0,0) 70%); top: -80px; left: -80px; pointer-events: none;"></div>
                        <div class="position-absolute rounded-circle" 
                             style="width: 220px; height: 220px; background: radial-gradient(circle, rgba(13,202,240,0.2) 0%, rgba(0,0,0,0) 70%); bottom: -60px; right: -60px; pointer-events: none;"></div>

                        <div class="position-relative z-1">
                            <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-25 border border-success border-opacity-50 text-success rounded-3 p-2 mb-3">
                                <?= \app\helpers\SvgHelper::icon('person-plus-fill', ['class' => 'fs-3 text-success']) ?>
                            </div>
                            <h3 class="fw-bold tracking-tight text-white mb-2">Buat Akun <span class="text-success">Baru</span></h3>
                            <p class="text-white-50 small mb-0">Mulai lacak portofolio dan eksekusi strategi saham terukur</p>
                        </div>

                        <div class="my-4 position-relative z-1 d-none d-md-block">
                            <div class="d-flex align-items-start gap-2 mb-3">
                                <span class="text-success mt-1"><?= \app\helpers\SvgHelper::icon('check-circle-fill') ?></span>
                                <div class="small text-light"><strong>Averaging Planner:</strong> Rancang alokasi modal dan kurangi risiko drawdown.</div>
                            </div>
                            <div class="d-flex align-items-start gap-2 mb-3">
                                <span class="text-info mt-1"><?= \app\helpers\SvgHelper::icon('check-circle-fill') ?></span>
                                <div class="small text-light"><strong>Watchlist Pribadi:</strong> Simpan saham favorit dengan sinyal otomatis.</div>
                            </div>
                            <div class="d-flex align-items-start gap-2">
                                <span class="text-warning mt-1"><?= \app\helpers\SvgHelper::icon('check-circle-fill') ?></span>
                                <div class="small text-light"><strong>Multi-Timeframe:</strong> Analisis swing &amp; tren harian tanpa ribet.</div>
                            </div>
                        </div>

                        <div class="position-relative z-1 pt-3 border-top border-secondary border-opacity-25 small text-white-50">
                            Gratis dan instan tanpa konfigurasi rumit.
                        </div>
                    </div>

                    <!-- Kolom Form Register Kanan -->
                    <div class="col-md-7 bg-white p-4 p-lg-5 d-flex flex-column justify-content-center">
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h3 class="fw-bold text-dark mb-0">Registrasi Akun</h3>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 small">Gratis</span>
                            </div>
                            <p class="text-muted small">Lengkapi formulir di bawah ini untuk memulai akses akun Anda.</p>
                        </div>

                        <?php $form = ActiveForm::begin([
                            'id' => 'register-form',
                            'fieldConfig' => [
                                'template' => "{label}\n{input}\n{error}",
                                'labelOptions' => ['class' => 'form-label fw-semibold small text-secondary mb-1'],
                                'inputOptions' => ['class' => 'form-control fs-6 border-light-subtle shadow-none'],
                                'errorOptions' => ['class' => 'invalid-feedback small'],
                            ],
                        ]); ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary mb-1" for="registerform-username">
                                <?= \app\helpers\SvgHelper::icon('person', ['class' => 'me-1 text-success']) ?> Username
                            </label>
                            <?= $form->field($model, 'username', [
                                'template' => "<div class=\"input-group\">\n<span class=\"input-group-text bg-light border-end-0 text-muted px-3\">" . \app\helpers\SvgHelper::icon('person') . "</span>\n{input}\n</div>\n{error}"
                            ])->textInput([
                                'autofocus' => true,
                                'placeholder' => 'Pilih username unik...',
                                'class' => 'form-control form-control-lg fs-6 border-start-0 ps-0'
                            ])->label(false) ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary mb-1" for="registerform-email">
                                <?= \app\helpers\SvgHelper::icon('envelope', ['class' => 'me-1 text-success']) ?> Alamat Email
                            </label>
                            <?= $form->field($model, 'email', [
                                'template' => "<div class=\"input-group\">\n<span class=\"input-group-text bg-light border-end-0 text-muted px-3\">" . \app\helpers\SvgHelper::icon('envelope') . "</span>\n{input}\n</div>\n{error}"
                            ])->textInput([
                                'type' => 'email',
                                'placeholder' => 'nama@email.com',
                                'class' => 'form-control form-control-lg fs-6 border-start-0 ps-0'
                            ])->label(false) ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-secondary mb-1" for="registerform-password">
                                <?= \app\helpers\SvgHelper::icon('lock', ['class' => 'me-1 text-success']) ?> Password
                            </label>
                            <?= $form->field($model, 'password', [
                                'template' => "<div class=\"input-group\">\n<span class=\"input-group-text bg-light border-end-0 text-muted px-3\">" . \app\helpers\SvgHelper::icon('lock') . "</span>\n{input}\n</div>\n{error}"
                            ])->passwordInput([
                                'placeholder' => 'Minimal 6 karakter...',
                                'class' => 'form-control form-control-lg fs-6 border-start-0 ps-0'
                            ])->label(false) ?>
                        </div>

                        <div class="d-grid mb-3">
                            <?= Html::submitButton(
                                \app\helpers\SvgHelper::icon('person-plus-fill', ['class' => 'me-2 fs-5 align-middle']) . 'Buat Akun Saya',
                                [
                                    'class' => 'btn btn-success btn-lg fw-bold shadow-sm py-2 fs-6 d-flex align-items-center justify-content-center transition-all',
                                    'name' => 'register-button'
                                ]
                            ) ?>
                        </div>

                        <div class="text-center pt-2">
                            <p class="small text-muted mb-0">
                                Sudah memiliki akun? 
                                <?= Html::a(
                                    'Masuk di sini ' . \app\helpers\SvgHelper::icon('arrow-right-short'), 
                                    ['/site/login'], 
                                    ['class' => 'fw-bold text-success text-decoration-none ms-1']
                                ) ?>
                            </p>
                        </div>

                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-3 small text-muted">
                <?= \app\helpers\SvgHelper::icon('shield-check', ['class' => 'text-success me-1']) ?>
                Data Anda tersimpan secara privat &amp; aman.
            </div>
        </div>
    </div>
</div>
