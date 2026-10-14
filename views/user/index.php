<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use app\models\PortfolioPosition;
use app\models\User;
use yii\bootstrap5\Html;
use yii\grid\GridView;
use yii\helpers\Url;

$this->title = 'User Management';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-management-index">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-people-fill text-primary me-2"></i><?= Html::encode($this->title) ?>
            </h1>
            <p class="text-muted small mb-0">
                Kelola akun pengguna aplikasi, hak akses/role (Admin/User), status akun, dan tinjau portofolio masing-masing.
            </p>
        </div>
        <div>
            <?= Html::a('<i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna', ['create'], [
                'class' => 'btn btn-primary btn-sm px-3 fw-bold shadow-sm',
            ]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
                'layout' => "{items}\n<div class='p-3 d-flex justify-content-between align-items-center border-top'>{summary}{pager}</div>",
                'columns' => [
                    [
                        'attribute' => 'id',
                        'headerOptions' => ['style' => 'width: 70px;', 'class' => 'bg-light text-muted small fw-semibold'],
                        'contentOptions' => ['class' => 'fw-bold text-muted'],
                    ],
                    [
                        'attribute' => 'username',
                        'header' => 'Pengguna',
                        'headerOptions' => ['class' => 'bg-light text-muted small fw-semibold'],
                        'format' => 'raw',
                        'value' => function (User $model) {
                            $isCurrentUser = $model->id === (int) Yii::$app->user->id;
                            $badgeYou = $isCurrentUser ? ' <span class="badge bg-secondary-subtle text-secondary ms-1">Anda</span>' : '';
                            return '<div class="fw-bold text-dark">' . Html::encode($model->username) . $badgeYou . '</div>'
                                . '<div class="small text-muted">' . Html::encode($model->email) . '</div>';
                        },
                    ],
                    [
                        'attribute' => 'role',
                        'header' => 'Role Hak Akses',
                        'headerOptions' => ['class' => 'bg-light text-muted small fw-semibold text-center', 'style' => 'width: 150px;'],
                        'contentOptions' => ['class' => 'text-center'],
                        'format' => 'raw',
                        'value' => function (User $model) {
                            if ($model->isAdmin()) {
                                return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-shield-lock-fill me-1"></i> Admin</span>';
                            }
                            return '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-person me-1"></i> User</span>';
                        },
                    ],
                    [
                        'header' => 'Portofolio Saham',
                        'headerOptions' => ['class' => 'bg-light text-muted small fw-semibold text-center', 'style' => 'width: 170px;'],
                        'contentOptions' => ['class' => 'text-center'],
                        'format' => 'raw',
                        'value' => function (User $model) {
                            $count = PortfolioPosition::find()->where(['user_id' => $model->id])->count();
                            return '<span class="badge bg-light text-dark border px-2 py-1"><i class="bi bi-briefcase me-1"></i> ' . $count . ' Posisi</span>';
                        },
                    ],
                    [
                        'attribute' => 'status',
                        'header' => 'Status Akun',
                        'headerOptions' => ['class' => 'bg-light text-muted small fw-semibold text-center', 'style' => 'width: 140px;'],
                        'contentOptions' => ['class' => 'text-center'],
                        'format' => 'raw',
                        'value' => function (User $model) {
                            if ($model->status === User::STATUS_ACTIVE) {
                                return '<span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i> Aktif</span>';
                            }
                            return '<span class="badge bg-secondary text-white px-2 py-1"><i class="bi bi-slash-circle me-1"></i> Nonaktif</span>';
                        },
                    ],
                    [
                        'attribute' => 'created_at',
                        'header' => 'Terdaftar Sejak',
                        'headerOptions' => ['class' => 'bg-light text-muted small fw-semibold text-center', 'style' => 'width: 150px;'],
                        'contentOptions' => ['class' => 'text-center small text-muted'],
                        'format' => 'raw',
                        'value' => fn(User $model) => $model->created_at ? date('d M Y', $model->created_at) : '-',
                    ],
                    [
                        'header' => 'Aksi',
                        'headerOptions' => ['class' => 'bg-light text-muted small fw-semibold text-end', 'style' => 'width: 220px;'],
                        'contentOptions' => ['class' => 'text-end'],
                        'format' => 'raw',
                        'value' => function (User $model) {
                            $isCurrentUser = $model->id === (int) Yii::$app->user->id;

                            $editBtn = Html::a('<i class="bi bi-pencil-square"></i>', ['update', 'id' => $model->id], [
                                'class' => 'btn btn-outline-secondary btn-sm',
                                'title' => 'Edit User',
                            ]);

                            $toggleRoleBtn = Html::a(
                                $model->isAdmin() ? '<i class="bi bi-arrow-down-circle" title="Ubah ke User"></i>' : '<i class="bi bi-shield-check" title="Promote ke Admin"></i>',
                                ['toggle-role', 'id' => $model->id],
                                [
                                    'class' => 'btn btn-outline-info btn-sm' . ($isCurrentUser ? ' disabled' : ''),
                                    'data-method' => 'post',
                                    'data-confirm' => "Ubah role pengguna '{$model->username}'?",
                                    'title' => 'Ganti Role',
                                ]
                            );

                            $toggleStatusBtn = Html::a(
                                $model->status === User::STATUS_ACTIVE ? '<i class="bi bi-pause-circle" title="Nonaktifkan"></i>' : '<i class="bi bi-play-circle" title="Aktifkan"></i>',
                                ['toggle-status', 'id' => $model->id],
                                [
                                    'class' => 'btn btn-outline-warning btn-sm' . ($isCurrentUser ? ' disabled' : ''),
                                    'data-method' => 'post',
                                    'data-confirm' => "Ubah status pengguna '{$model->username}'?",
                                    'title' => 'Ubah Status',
                                ]
                            );

                            $deleteBtn = Html::a('<i class="bi bi-trash"></i>', ['delete', 'id' => $model->id], [
                                'class' => 'btn btn-outline-danger btn-sm' . ($isCurrentUser ? ' disabled' : ''),
                                'data-method' => 'post',
                                'data-confirm' => "Apakah Anda yakin ingin menghapus user '{$model->username}' beserta seluruh portofolionya?",
                                'title' => 'Hapus User',
                            ]);

                            return "<div class='btn-group btn-group-sm'>{$editBtn}{$toggleRoleBtn}{$toggleStatusBtn}{$deleteBtn}</div>";
                        },
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
