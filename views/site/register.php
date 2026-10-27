<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var app\models\form\RegisterForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Daftar Akun Baru - Stock Analyzer Pro';
?>

<div class="auth-page-wrapper py-3 py-md-5">
    <div class="row justify-content-center align-items-center">
        <div class="col-12 col-md-11 col-lg-10 col-xl-9" style="max-width: 960px;">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden auth-card bg-white">
                <div class="row g-0">
                    <!-- ====================================================================
                         KOLOM KIRI: ONBOARDING & VALUE PROPOSITION TERMINAL
                         ==================================================================== -->
                    <div class="col-md-5 text-white p-4 p-lg-5 d-flex flex-column justify-content-between position-relative overflow-hidden auth-sidebar">
                        <!-- Ambient Radial Glows -->
                        <div class="auth-sidebar-glow-1"></div>
                        <div class="auth-sidebar-glow-2"></div>

                        <!-- Brand Header -->
                        <div class="position-relative z-2">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="brand-logo-badge brand-logo-badge-emerald">
                                    <i class="bi bi-person-plus-fill"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-5 text-white lh-1">
                                        Stock Analyzer <span class="text-emerald">Pro</span>
                                    </div>
                                    <span class="text-slate-400" style="font-size: 0.72rem; letter-spacing: 0.02em;">
                                        TRADER ONBOARDING HUB
                                    </span>
                                </div>
                            </div>
                            <p class="text-slate-300 small mb-0" style="line-height: 1.5; font-size: 0.84rem;">
                                Bergabung dengan komunitas trader mandiri dan eksekusi strategi saham berbasis volume serta probabilitas terukur.
                            </p>
                        </div>

                        <!-- Live Terminal Snapshot Widget -->
                        <div class="position-relative z-2 my-4 d-none d-md-block">
                            <div class="auth-terminal-widget p-3 rounded-3 mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-inline-flex align-items-center gap-1.5">
                                        <span class="live-pulse-dot"></span>
                                        <span class="small fw-bold text-white text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.05em;">AKSES ANGGOTA PENUH</span>
                                    </div>
                                    <span class="badge text-white fw-bold" style="background-color: #059669 !important; font-size: 0.68rem;">100% Gratis</span>
                                </div>
                                <div class="text-white mb-2">
                                    <div class="fw-bold fs-6">8,000+ Emiten Wall Street &bull; Crypto 24/7</div>
                                    <div class="text-slate-400" style="font-size: 0.72rem;">Platform Analisis Teknikal Terpadu</div>
                                </div>
                                <div class="pt-2 border-top border-white border-opacity-10 d-flex justify-content-between text-slate-300" style="font-size: 0.72rem;">
                                    <span><i class="bi bi-shield-check text-emerald me-1"></i>Averaging Plan</span>
                                    <span><i class="bi bi-star-fill text-warning me-1"></i>Watchlist</span>
                                    <span><i class="bi bi-cpu text-info me-1"></i>AI Blog Info</span>
                                </div>
                            </div>

                            <!-- Value Feature Highlights -->
                            <div class="d-flex flex-column gap-2.5 auth-feature-list">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="feature-icon-bullet text-emerald"><i class="bi bi-check2-circle"></i></span>
                                    <div class="text-slate-300" style="font-size: 0.8rem;">
                                        <strong class="text-white">Scanner Volume Lengkap:</strong> Akses radar RVOL, Confluence, &amp; Double Bottom.
                                    </div>
                                </div>
                                <div class="d-flex align-items-start gap-2">
                                    <span class="feature-icon-bullet text-info"><i class="bi bi-check2-circle"></i></span>
                                    <div class="text-slate-300" style="font-size: 0.8rem;">
                                        <strong class="text-white">Averaging &amp; Portfolio Tracker:</strong> Atur modal masuk bertahap tanpa spekulasi buta.
                                    </div>
                                </div>
                                <div class="d-flex align-items-start gap-2">
                                    <span class="feature-icon-bullet text-warning"><i class="bi bi-check2-circle"></i></span>
                                    <div class="text-slate-300" style="font-size: 0.8rem;">
                                        <strong class="text-white">Trading Plan Terarah:</strong> Rekomendasi level Entry, TP, &amp; SL disiplin.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar Footer -->
                        <div class="position-relative z-2 pt-3 border-top border-white border-opacity-10 text-slate-400 d-flex align-items-center gap-1.5" style="font-size: 0.75rem;">
                            <i class="bi bi-lightning-charge-fill text-warning"></i>
                            <span>Akses Instan Tanpa Kartu Kredit &bull; Data Terenkripsi Aman</span>
                        </div>
                    </div>

                    <!-- ====================================================================
                         KOLOM KANAN: FORM REGISTRASI CLEAN & MODERN
                         ==================================================================== -->
                    <div class="col-md-7 p-4 p-lg-5 d-flex flex-column justify-content-center bg-white">
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge auth-badge-pill-emerald">
                                    <i class="bi bi-stars me-1 text-emerald"></i> Pendaftaran Akun Cepat
                                </span>
                            </div>
                            <h2 class="h3 fw-bold text-slate-900 mb-1" style="color: #0f172a; letter-spacing: -0.02em;">
                                Buat Akun Trader Anda
                            </h2>
                            <p class="text-slate-700 small mb-0" style="color: #334155; font-weight: 500;">
                                Bergabung dalam hitungan detik untuk mulai memantau sinyal saham Wall Street.
                            </p>
                        </div>

                        <?php $form = ActiveForm::begin([
                            'id' => 'register-form',
                            'enableClientValidation' => true,
                            'options' => ['class' => 'auth-form-needs-validation'],
                            'fieldConfig' => [
                                'template' => "{label}\n{input}\n{error}",
                                'labelOptions' => ['class' => 'form-label fw-bold text-dark small mb-1.5'],
                                'errorOptions' => ['class' => 'invalid-feedback small d-block mt-1'],
                            ],
                        ]); ?>

                        <!-- Input Username -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small mb-1.5" for="registerform-username">
                                Username
                            </label>
                            <div class="auth-input-group">
                                <span class="auth-input-icon">
                                    <i class="bi bi-person"></i>
                                </span>
                                <?= $form->field($model, 'username', [
                                    'template' => "{input}\n{error}",
                                    'options' => ['class' => 'w-100 mb-0'],
                                ])->textInput([
                                    'autofocus' => true,
                                    'placeholder' => 'Pilih username unik (min. 3 karakter)...',
                                    'class' => 'form-control auth-input-control',
                                    'id' => 'registerform-username',
                                ])->label(false) ?>
                            </div>
                        </div>

                        <!-- Input Email -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small mb-1.5" for="registerform-email">
                                Alamat Email
                            </label>
                            <div class="auth-input-group">
                                <span class="auth-input-icon">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <?= $form->field($model, 'email', [
                                    'template' => "{input}\n{error}",
                                    'options' => ['class' => 'w-100 mb-0'],
                                ])->textInput([
                                    'type' => 'email',
                                    'placeholder' => 'nama@domain.com',
                                    'class' => 'form-control auth-input-control',
                                    'id' => 'registerform-email',
                                ])->label(false) ?>
                            </div>
                        </div>

                        <!-- Input Password -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small mb-1.5" for="registerform-password">
                                Password
                            </label>
                            <div class="auth-input-group position-relative">
                                <span class="auth-input-icon">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <?= $form->field($model, 'password', [
                                    'template' => "{input}\n{error}",
                                    'options' => ['class' => 'w-100 mb-0'],
                                ])->passwordInput([
                                    'placeholder' => 'Minimal 6 karakter...',
                                    'class' => 'form-control auth-input-control pe-5',
                                    'id' => 'registerform-password',
                                ])->label(false) ?>
                                <button type="button" 
                                        class="btn btn-link auth-password-toggle p-0 text-slate-400 text-decoration-none" 
                                        title="Tampilkan / Sembunyikan Password"
                                        onclick="togglePasswordVisibility('registerform-password', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text text-slate-600 fw-medium small mt-1" style="font-size: 0.75rem;">
                                Gunakan minimal 6 karakter dengan kombinasi yang aman.
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid mb-3.5">
                            <?= Html::submitButton(
                                '<span>Buat Akun Saya</span> <i class="bi bi-person-plus-fill ms-2"></i>',
                                [
                                    'class' => 'btn btn-emerald-submit py-2.5 fw-bold shadow-sm d-inline-flex align-items-center justify-content-center text-white',
                                    'name' => 'register-button'
                                ]
                            ) ?>
                        </div>

                        <!-- Switcher to Login -->
                        <div class="text-center pt-2 border-top">
                            <p class="small text-slate-500 mb-0">
                                Sudah memiliki akun trader? 
                                <?= Html::a(
                                    'Masuk ke Platform <i class="bi bi-arrow-up-right ms-0.5"></i>', 
                                    ['/site/login'], 
                                    ['class' => 'fw-bold text-primary text-decoration-none ms-1 hover-underline']
                                ) ?>
                            </p>
                        </div>

                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            </div>

            <!-- Security Footer Note -->
            <div class="text-center mt-3 small text-slate-500">
                <i class="bi bi-shield-check text-emerald me-1"></i>
                Privasi Terjamin &bull; Data Akun Anda Tersimpan Secara Aman &amp; Terenkripsi
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================
     CLIENT SCRIPT & MODERN FINTECH STYLING
     ==================================================================== -->
<script>
function togglePasswordVisibility(fieldId, btn) {
    var input = document.getElementById(fieldId);
    if (!input) return;
    var icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
}
</script>

<style>
/* Base Theme Tokens */
.text-slate-900 { color: #0f172a !important; }
.text-slate-700 { color: #334155 !important; }
.text-slate-600 { color: #475569 !important; }
.text-slate-500 { color: #334155 !important; } /* High contrast Slate 700 (8.5:1) */
.text-slate-400 { color: #94a3b8 !important; }
.text-slate-300 { color: #cbd5e1 !important; }
.text-emerald { color: #10b981 !important; }
.text-cyan { color: #06b6d4 !important; }
.bg-emerald-subtle { background-color: rgba(16, 185, 129, 0.15) !important; }

/* Wrapper & Card */
.auth-page-wrapper {
    min-height: 75vh;
}
.auth-card {
    border: 1px solid rgba(226, 232, 240, 0.9) !important;
    box-shadow: 0 20px 45px -15px rgba(15, 23, 42, 0.08), 0 0 1px 1px rgba(226, 232, 240, 0.6) !important;
}

/* Left Column Sidebar */
.auth-sidebar {
    background: linear-gradient(155deg, #090e1a 0%, #0f172a 45%, #1e1b4b 100%);
    position: relative;
}
.auth-sidebar-glow-1 {
    position: absolute;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.18) 0%, transparent 65%);
    top: -60px;
    left: -60px;
    pointer-events: none;
    z-index: 1;
}
.auth-sidebar-glow-2 {
    position: absolute;
    width: 240px;
    height: 240px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 65%);
    bottom: -40px;
    right: -40px;
    pointer-events: none;
    z-index: 1;
}

/* Brand Badge */
.brand-logo-badge {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 1.15rem;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.35);
}
.brand-logo-badge-emerald {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%) !important;
    box-shadow: 0 4px 10px rgba(5, 150, 105, 0.35) !important;
}

/* Live Terminal Mini-Widget */
.auth-terminal-widget {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.live-pulse-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background-color: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: livePulseAuth 2s infinite;
}
@keyframes livePulseAuth {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.feature-icon-bullet {
    font-size: 1rem;
    line-height: 1.2;
    flex-shrink: 0;
}

/* Right Column Elements */
.auth-badge-pill-emerald {
    background: #dcfce7 !important;
    color: #14532d !important;
    border: 1px solid #86efac !important;
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Modern Integrated Input Group */
.auth-input-group {
    display: flex;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    transition: all 0.2s ease-in-out;
    position: relative;
    padding-left: 0.75rem;
}
.auth-input-group:focus-within {
    border-color: #059669 !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1) !important;
}
.auth-input-icon {
    color: #94a3b8;
    font-size: 1.15rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.5rem;
    transition: color 0.2s ease;
}
.auth-input-group:focus-within .auth-input-icon {
    color: #059669;
}
.auth-input-control {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    font-size: 0.92rem;
    color: #0f172a;
    padding: 0.65rem 0.5rem 0.65rem 0;
    height: auto;
}
.auth-input-control::placeholder {
    color: #94a3b8;
}
.auth-input-group:has(.is-invalid) {
    border-color: #e11d48 !important;
    box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.1) !important;
}
.auth-password-toggle {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.1rem;
    z-index: 4;
}
.auth-password-toggle:hover {
    color: #1e293b !important;
}

/* Submit Button Emerald */
.btn-emerald-submit {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%) !important;
    border: none !important;
    border-radius: 0.65rem !important;
    font-size: 0.95rem;
    letter-spacing: -0.01em;
    transition: all 0.2s ease-in-out;
}
.btn-emerald-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3) !important;
}
.hover-underline:hover {
    text-decoration: underline !important;
}
</style>
