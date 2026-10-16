<?php

/** @var yii\web\View $this */
/** @var app\models\form\ProfileForm $model */
/** @var app\models\User $user */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Profil Saya & Pengaturan Akun';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-profile py-3">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-primary bg-gradient text-white p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px; font-size: 1.8rem;">
                            👤
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0 text-white"><?= Html::encode($user->username) ?></h4>
                            <div class="small text-white-50">
                                Role: <span class="badge bg-light text-dark fw-bold"><?= strtoupper($user->role) ?></span>
                                <span class="mx-1">•</span>
                                Terdaftar: <?= date('d M Y', $user->created_at ?: time()) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <?php $form = ActiveForm::begin([
                        'id' => 'profile-form',
                    ]); ?>

                    <h5 class="fw-bold text-dark mb-3">
                        <?= \app\helpers\SvgHelper::icon('person-lines-fill', ['class' => 'text-primary me-2']) ?>Informasi Pengguna
                    </h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <?= $form->field($model, 'username')->textInput([
                                'maxlength' => true,
                                'class' => 'form-control',
                            ])->hint('Nama pengguna unik untuk login ke sistem.') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'email')->textInput([
                                'type' => 'email',
                                'maxlength' => true,
                                'class' => 'form-control',
                            ])->hint('Alamat email aktif untuk notifikasi alert saham.') ?>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="fw-bold text-dark mb-2">
                        <?= \app\helpers\SvgHelper::icon('shield-lock', ['class' => 'text-primary me-2']) ?>Ubah Password
                    </h5>
                    <p class="text-muted small mb-3">
                        Biarkan kolom password di bawah ini kosong jika Anda tidak ingin mengganti password Anda.
                    </p>

                    <div class="mb-3">
                        <?= $form->field($model, 'old_password')->passwordInput([
                            'placeholder' => 'Masukkan password saat ini',
                            'class' => 'form-control',
                            'autocomplete' => 'current-password',
                        ])->hint('Wajib diisi jika Anda ingin menentukan password baru.') ?>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <?= $form->field($model, 'new_password')->passwordInput([
                                'placeholder' => 'Minimal 6 karakter',
                                'class' => 'form-control',
                                'autocomplete' => 'new-password',
                            ]) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'new_password_repeat')->passwordInput([
                                'placeholder' => 'Ulangi password baru',
                                'class' => 'form-control',
                                'autocomplete' => 'new-password',
                            ]) ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <?= Html::a(\app\helpers\SvgHelper::icon('arrow-left', ['class' => 'me-1']) . ' Kembali ke Dashboard', ['/dashboard/index'], [
                            'class' => 'btn btn-outline-secondary',
                        ]) ?>
                        <?= Html::submitButton(\app\helpers\SvgHelper::icon('check2-circle', ['class' => 'me-1']) . ' Simpan Perubahan Profil', [
                            'class' => 'btn btn-primary px-4 fw-bold shadow-sm',
                        ]) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
