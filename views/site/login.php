<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var app\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Masuk ke Platform - Stock Analyzer Pro';
?>

<div class="site-login py-5">
    <div class="row justify-content-center align-items-center min-vh-75">
        <div class="col-12 col-md-10 col-lg-9 col-xl-8">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="row g-0">
                    <!-- Kolon Branding / Info Kiri -->
                    <div class="col-md-5 bg-dark text-white p-4 p-lg-5 d-flex flex-column justify-content-between position-relative overflow-hidden" 
                         style="background: linear-gradient(145deg, #0b1727 0%, #112338 60%, #0d3b66 100%);">
                        
                        <!-- Dekorasi background glow -->
                        <div class="position-absolute rounded-circle" 
                             style="width: 250px; height: 250px; background: radial-gradient(circle, rgba(13,110,253,0.25) 0%, rgba(0,0,0,0) 70%); top: -80px; left: -80px; pointer-events: none;"></div>
                        <div class="position-absolute rounded-circle" 
                             style="width: 220px; height: 220px; background: radial-gradient(circle, rgba(32,201,151,0.2) 0%, rgba(0,0,0,0) 70%); bottom: -60px; right: -60px; pointer-events: none;"></div>

                        <div class="position-relative z-1">
                            <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-25 border border-primary border-opacity-50 text-primary rounded-3 p-2 mb-3">
                                <?= \app\helpers\SvgHelper::icon('graph-up-arrow', ['class' => 'fs-3 text-info']) ?>
                            </div>
                            <h3 class="fw-bold tracking-tight text-white mb-2">Stock Analyzer <span class="text-info">Pro</span></h3>
                            <p class="text-white-50 small mb-0">Platform Intelijen Pasar &amp; Deteksi Konfluensi Pola Saham Terpadu</p>
                        </div>

                        <div class="my-4 position-relative z-1 d-none d-md-block">
                            <div class="d-flex align-items-start gap-2 mb-3">
                                <span class="text-success mt-1"><?= \app\helpers\SvgHelper::icon('check-circle-fill') ?></span>
                                <div class="small text-light"><strong>Real-time Scanners:</strong> Deteksi Breakout, RVOL &amp; Divergence instan.</div>
                            </div>
                            <div class="d-flex align-items-start gap-2 mb-3">
                                <span class="text-info mt-1"><?= \app\helpers\SvgHelper::icon('check-circle-fill') ?></span>
                                <div class="small text-light"><strong>Kalkulator Averaging:</strong> Rencana TP bertahap &amp; manajemen risiko modal.</div>
                            </div>
                            <div class="d-flex align-items-start gap-2">
                                <span class="text-warning mt-1"><?= \app\helpers\SvgHelper::icon('check-circle-fill') ?></span>
                                <div class="small text-light"><strong>Trading Plan Otomatis:</strong> Golden Ratio Fibonacci &amp; Moving Average Cross.</div>
                            </div>
                        </div>

                        <div class="position-relative z-1 pt-3 border-top border-secondary border-opacity-25 small text-white-50">
                            Terlindungi dengan enkripsi sesi aman &amp; kontrol akses berbasis role.
                        </div>
                    </div>

                    <!-- Kolom Form Login Kanan -->
                    <div class="col-md-7 bg-white p-4 p-lg-5 d-flex flex-column justify-content-center">
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h3 class="fw-bold text-dark mb-0">Selamat Datang</h3>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 small">Portal Trader</span>
                            </div>
                            <p class="text-muted small">Silakan masukkan identitas akun untuk mengakses terminal analisis saham Anda.</p>
                        </div>

                        <?php $form = ActiveForm::begin([
                            'id' => 'login-form',
                            'fieldConfig' => [
                                'template' => "{label}\n<div class=\"input-group input-group-lg\">{input}</div>\n{error}",
                                'labelOptions' => ['class' => 'form-label fw-semibold small text-secondary mb-1'],
                                'inputOptions' => ['class' => 'form-control fs-6 border-light-subtle shadow-none'],
                                'errorOptions' => ['class' => 'invalid-feedback small'],
                            ],
                        ]); ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary mb-1" for="loginform-username">
                                <?= \app\helpers\SvgHelper::icon('person', ['class' => 'me-1 text-primary']) ?> Username
                            </label>
                            <?= $form->field($model, 'username', [
                                'template' => "<div class=\"input-group\">\n<span class=\"input-group-text bg-light border-end-0 text-muted px-3\">" . \app\helpers\SvgHelper::icon('person') . "</span>\n{input}\n</div>\n{error}"
                            ])->textInput([
                                'autofocus' => true,
                                'placeholder' => 'Ketik username Anda...',
                                'class' => 'form-control form-control-lg fs-6 border-start-0 ps-0'
                            ])->label(false) ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary mb-1" for="loginform-password">
                                <?= \app\helpers\SvgHelper::icon('lock', ['class' => 'me-1 text-primary']) ?> Password
                            </label>
                            <?= $form->field($model, 'password', [
                                'template' => "<div class=\"input-group\">\n<span class=\"input-group-text bg-light border-end-0 text-muted px-3\">" . \app\helpers\SvgHelper::icon('lock') . "</span>\n{input}\n</div>\n{error}"
                            ])->passwordInput([
                                'placeholder' => 'Masukkan kata sandi...',
                                'class' => 'form-control form-control-lg fs-6 border-start-0 ps-0'
                            ])->label(false) ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <?= $form->field($model, 'rememberMe', [
                                'options' => ['class' => 'mb-0']
                            ])->checkbox([
                                'class' => 'form-check-input mt-0',
                                'labelOptions' => ['class' => 'form-check-label small text-muted ms-1 user-select-none'],
                            ])->label('Ingat sesi saya di browser ini') ?>
                        </div>

                        <div class="d-grid mb-3">
                            <?= Html::submitButton(
                                \app\helpers\SvgHelper::icon('box-arrow-in-right', ['class' => 'me-2 fs-5 align-middle']) . 'Masuk ke Dashboard',
                                [
                                    'class' => 'btn btn-primary btn-lg fw-bold shadow-sm py-2 fs-6 d-flex align-items-center justify-content-center transition-all',
                                    'name' => 'login-button'
                                ]
                            ) ?>
                        </div>

                        <div class="text-center pt-2">
                            <p class="small text-muted mb-0">
                                Belum memiliki akun trader? 
                                <?= Html::a(
                                    'Daftar Akun Baru ' . \app\helpers\SvgHelper::icon('arrow-right-short'), 
                                    ['/site/register'], 
                                    ['class' => 'fw-bold text-primary text-decoration-none ms-1']
                                ) ?>
                            </p>
                        </div>

                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Help / Demo Note -->
            <div class="text-center mt-3 small text-muted">
                <?= \app\helpers\SvgHelper::icon('shield-check', ['class' => 'text-success me-1']) ?>
                Sistem Terverifikasi • Multi-Timeframe Intraday &amp; Daily Market Analysis
            </div>
        </div>
    </div>
</div>
