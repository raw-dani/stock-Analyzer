<?php

/** @var yii\web\View $this */
/** @var app\models\User $model */

use app\models\User;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Tambah Pengguna Baru';
$this->params['breadcrumbs'][] = ['label' => 'User Management', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-create py-3">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-person-plus-fill text-primary me-2"></i><?= Html::encode($this->title) ?>
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php $form = ActiveForm::begin([
                        'id' => 'user-create-form',
                    ]); ?>

                    <?= $form->field($model, 'username')->textInput([
                        'maxlength' => true,
                        'placeholder' => 'Contoh: johndoe',
                    ]) ?>

                    <?= $form->field($model, 'email')->textInput([
                        'type' => 'email',
                        'maxlength' => true,
                        'placeholder' => 'Contoh: john@example.com',
                    ]) ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="user-raw_password">Password</label>
                        <input type="password" id="user-raw_password" class="form-control" name="User[raw_password]" required placeholder="Password untuk user ini">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'role')->dropDownList(User::ROLES, [
                                'class' => 'form-select',
                            ]) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'status')->dropDownList([
                                User::STATUS_ACTIVE => 'Aktif',
                                User::STATUS_DELETED => 'Nonaktif',
                            ], [
                                'class' => 'form-select',
                            ]) ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <?= Html::a('<i class="bi bi-arrow-left me-1"></i> Batal', ['index'], [
                            'class' => 'btn btn-outline-secondary px-3',
                        ]) ?>
                        <?= Html::submitButton('<i class="bi bi-check-circle me-1"></i> Simpan Pengguna', [
                            'class' => 'btn btn-primary px-4 fw-bold shadow-sm',
                        ]) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
