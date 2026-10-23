<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $stats */
/** @var string $search */
/** @var string $selectedStatus */
/** @var string $selectedCategory */
/** @var array $categories */

use app\models\BlogPost;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\helpers\Url;

$this->title = 'Kelola Blog & Artikel Analisis | Admin';
$this->params['breadcrumbs'][] = ['label' => 'Blog Info Saham', 'url' => ['/blog/index']];
$this->params['breadcrumbs'][] = 'Manajemen Admin';

$models = $dataProvider->getModels();
?>

<div class="blog-admin-container pb-5">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-newspaper text-primary me-2"></i> Manajemen Artikel Blog
            </h1>
            <p class="text-muted small mb-0">
                Kelola publikasi hasil analisis saham, edit konten dwibahasa, atau tinjau statistik performa artikel.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= Url::to(['/blog/create']) ?>" class="btn btn-primary rounded-pill px-3 shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Buat Artikel Manual
            </a>
            <a href="<?= Url::to(['/blog/settings']) ?>" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-gear me-1"></i> Pengaturan SEO &amp; AI
            </a>
            <a href="<?= Url::to(['/blog/index']) ?>" target="_blank" class="btn btn-outline-info rounded-pill px-3">
                <i class="bi bi-box-arrow-up-right me-1"></i> Buka Blog Publik
            </a>
        </div>
    </div>

    <!-- KPI Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <div class="text-muted small fw-semibold text-uppercase">Total Artikel</div>
                <div class="h3 mb-0 fw-bold text-dark mt-1"><?= number_format((int) $stats['total']) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <div class="text-muted small fw-semibold text-uppercase">Published (Tayang)</div>
                <div class="h3 mb-0 fw-bold text-success mt-1"><?= number_format((int) $stats['published']) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <div class="text-muted small fw-semibold text-uppercase">Draft (Peninjauan)</div>
                <div class="h3 mb-0 fw-bold text-warning mt-1"><?= number_format((int) $stats['draft']) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <div class="text-muted small fw-semibold text-uppercase">Total Pembaca (Views)</div>
                <div class="h3 mb-0 fw-bold text-primary mt-1"><?= number_format((int) $stats['total_views']) ?></div>
            </div>
        </div>
    </div>

    <!-- Filter & Pencarian Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="get" action="<?= Url::to(['/blog/admin']) ?>" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" value="<?= Html::encode($search) ?>" class="form-control bg-light border-0" placeholder="Cari simbol saham, judul artikel...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select bg-light border-0" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= Html::encode($cat) ?>" <?= ($selectedCategory ?? '') === $cat ? 'selected' : '' ?>>
                                <?= Html::encode($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select bg-light border-0" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <?php foreach (BlogPost::STATUSES as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $selectedStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    <?php if (!empty($search) || !empty($selectedStatus) || !empty($selectedCategory)): ?>
                        <a href="<?= Url::to(['/blog/admin']) ?>" class="btn btn-outline-secondary" title="Reset">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Daftar Artikel -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-secondary fw-semibold">
                    <tr>
                        <th class="ps-4">Simbol / Emiten</th>
                        <th>Kategori &amp; TF</th>
                        <th>Judul Artikel (ID)</th>
                        <th>Tanggal Sesi</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($models)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                Belum ada artikel yang ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($models as $post): ?>
                            <?php
                            /** @var BlogPost $post */
                            $statusBadge = match ($post->status) {
                                BlogPost::STATUS_PUBLISHED => 'bg-success',
                                BlogPost::STATUS_DRAFT => 'bg-warning text-dark',
                                default => 'bg-secondary',
                            };
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold fs-6 text-dark"><?= Html::encode($post->symbol) ?></div>
                                    <div class="small text-muted"><?= Html::encode($post->stock?->name ?? 'Stock') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 me-1">
                                        <i class="bi bi-tag-fill me-1"></i><?= Html::encode($post->category) ?>
                                    </span>
                                    <span class="badge bg-light text-dark border"><?= Html::encode($post->time_frame) ?></span>
                                </td>
                                <td>
                                    <a href="<?= Url::to(['/blog/view', 'slug' => $post->slug_id]) ?>" target="_blank" class="fw-semibold text-dark text-decoration-none hover-primary">
                                        <?= Html::encode($post->title_id) ?>
                                    </a>
                                    <div class="small text-muted" style="max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        EN: <?= Html::encode($post->title_en) ?>
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    <?= Html::encode($post->analysis_date) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $statusBadge ?> px-2 py-1">
                                        <?= BlogPost::STATUSES[$post->status] ?? $post->status ?>
                                    </span>
                                </td>
                                <td class="small fw-bold">
                                    <i class="bi bi-eye text-muted me-1"></i> <?= number_format($post->views_count) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= Url::to(['/blog/view', 'slug' => $post->slug_id]) ?>" target="_blank" class="btn btn-outline-info" title="Lihat Artikel">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= Url::to(['/blog/update', 'id' => $post->id]) ?>" class="btn btn-outline-primary" title="Edit Artikel">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?= Html::a('<i class="bi bi-trash"></i>', ['/blog/delete', 'id' => $post->id], [
                                            'class' => 'btn btn-outline-danger',
                                            'title' => 'Hapus Artikel',
                                            'data' => [
                                                'confirm' => "Apakah Anda yakin ingin menghapus artikel {$post->symbol} ini?",
                                                'method' => 'post',
                                            ],
                                        ]) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($dataProvider->pagination->pageCount > 1): ?>
            <div class="p-3 border-top d-flex justify-content-center">
                <?= LinkPager::widget([
                    'pagination' => $dataProvider->pagination,
                    'options' => ['class' => 'pagination pagination-sm mb-0'],
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
