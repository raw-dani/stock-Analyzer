<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var app\models\BlogPost|null $featuredPost */
/** @var string $search */
/** @var string $selectedCategory */
/** @var string $selectedStrategy */
/** @var string $selectedTag */
/** @var array $categories */
/** @var array $strategies */

use app\models\BlogPost;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\helpers\Url;

$this->title = 'Blog Info Analisis Saham & Trading Plan AI | US Stock Analyzer';
$this->params['breadcrumbs'][] = 'Blog Info Saham';

// SEO Meta Tags untuk Halaman Indeks Blog
$this->registerMetaTag([
    'name' => 'description',
    'content' => 'Portal analisis teknikal saham Wall Street & crypto terkini berbasis AI, candlestick multi-timeframe, volume breakout, dan trading plan disiplin.',
]);
$this->registerMetaTag(['name' => 'keywords', 'content' => 'analisis saham, info saham, stock analysis, trading plan, double bottom, volume breakout, wall street']);
$this->registerMetaTag(['property' => 'og:title', 'content' => $this->title]);
$this->registerMetaTag(['property' => 'og:description', 'content' => 'Ulasan teknikal emiten AS & sinyal trading plan kuantitatif dwibahasa berbasis AI.']);
$this->registerMetaTag(['property' => 'og:type', 'content' => 'website']);
$this->registerMetaTag(['property' => 'og:url', 'content' => Url::to(['/blog/index'], true)]);
$this->registerLinkTag(['rel' => 'canonical', 'href' => Url::to(['/blog/index'], true)]);

$models = $dataProvider->getModels();
$isAdmin = Yii::$app->user && !Yii::$app->user->isGuest && Yii::$app->user->identity->isAdmin();
?>

<div class="blog-index-container pb-5">
    <!-- Header Hero Banner -->
    <div class="p-4 p-md-5 mb-4 rounded-4 text-white shadow-sm" style="background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 50%, #415a77 100%);">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-primary bg-opacity-25 border border-primary border-opacity-50 text-info small mb-3">
                    <span class="spinner-grow spinner-grow-sm text-info" role="status" style="width: 0.6rem; height: 0.6rem;"></span>
                    <span>AI-Powered Quantitative Insights &bull; Bilingual Analysis</span>
                </div>
                <h1 class="display-5 fw-bold mb-2">📰 Blog Info &amp; Analisis Saham</h1>
                <p class="lead text-light text-opacity-75 mb-0" style="max-width: 680px;">
                    Eksplorasi analisa teknikal presisi, dinamika volume breakout, dan trading plan terstruktur untuk saham-saham pilihan pasar modal US &amp; Global.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <?php if ($isAdmin): ?>
                    <a href="<?= Url::to(['/blog/admin']) ?>" class="btn btn-warning fw-bold px-3 py-2 shadow-sm">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard Admin Blog
                    </a>
                <?php endif; ?>
                <a href="<?= Url::to(['/scanner/index']) ?>" class="btn btn-outline-light px-3 py-2 ms-2 shadow-sm">
                    <i class="bi bi-search me-1"></i> Buka Scanner
                </a>
            </div>
        </div>
    </div>

    <!-- Filter & Pencarian Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body p-3">
            <form method="get" action="<?= Url::to(['/blog/index']) ?>" class="row g-2 align-items-center">
                <?php if (!empty($selectedCategory)): ?>
                    <input type="hidden" name="category" value="<?= Html::encode($selectedCategory) ?>">
                <?php endif; ?>
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" value="<?= Html::encode($search) ?>" class="form-control bg-light border-0" placeholder="Cari simbol (cth: AAPL, NVDA), judul, atau tag...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="strategy" class="form-select bg-light border-0" onchange="this.form.submit()">
                        <option value="">Semua Setup Strategi</option>
                        <?php foreach ($strategies as $strat): ?>
                            <option value="<?= Html::encode($strat) ?>" <?= $selectedStrategy === $strat ? 'selected' : '' ?>>
                                <?= Html::encode(ucwords(str_replace('-', ' ', $strat))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    <?php if (!empty($search) || !empty($selectedStrategy) || !empty($selectedCategory) || !empty($selectedTag)): ?>
                        <a href="<?= Url::to(['/blog/index']) ?>" class="btn btn-outline-secondary" title="Reset Filter">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!empty($selectedTag)): ?>
                <div class="mt-2 pt-2 border-top d-flex align-items-center gap-2">
                    <span class="small text-muted">Filter Tag Aktif:</span>
                    <span class="badge bg-primary px-2 py-1">
                        <?= Html::encode($selectedTag) ?>
                        <a href="<?= Url::to(['/blog/index', 'q' => $search, 'category' => $selectedCategory, 'strategy' => $selectedStrategy]) ?>" class="text-white text-decoration-none ms-1">&times;</a>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Category Pills Bar (Filter Kategori Cepat) -->
    <?php if (!empty($categories)): ?>
        <div class="d-flex align-items-center gap-2 overflow-auto pb-2 mb-4" style="white-space: nowrap;">
            <span class="small fw-bold text-muted text-uppercase me-1"><i class="bi bi-folder2-open me-1"></i> Kategori:</span>
            <a href="<?= Url::to(['/blog/index', 'q' => $search, 'strategy' => $selectedStrategy]) ?>" 
               class="btn btn-sm rounded-pill px-3 <?= empty($selectedCategory) ? 'btn-primary shadow-sm' : 'btn-light border text-dark' ?>">
                Semua Kategori
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?= Url::to(['/blog/index', 'category' => $cat, 'q' => $search]) ?>" 
                   class="btn btn-sm rounded-pill px-3 <?= ($selectedCategory === $cat) ? 'btn-primary shadow-sm' : 'btn-light border text-dark' ?>">
                    <i class="bi bi-tag-fill me-1 small"></i><?= Html::encode($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Featured Post Hero Card (Bila ada) -->
    <?php if ($featuredPost !== null && empty($search) && empty($selectedStrategy) && empty($selectedCategory) && empty($selectedTag)): ?>
        <?php
        $bias = $featuredPost->getBias();
        $biasClass = $bias === 'BULLISH' ? 'success' : ($bias === 'BEARISH' ? 'danger' : 'secondary');
        $imgUrl = !empty($featuredPost->featured_image) ? Yii::getAlias('@web') . $featuredPost->featured_image : null;
        ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
            <div class="row g-0">
                <div class="col-lg-7" style="min-height: 280px; max-height: 420px; overflow: hidden; background: #0b0f19;">
                    <?php if ($imgUrl): ?>
                        <img src="<?= Html::encode($imgUrl) ?>"
                             alt="Analisis Chart <?= Html::encode($featuredPost->symbol) ?>"
                             class="w-100 h-100 object-fit-cover"
                             loading="eager"
                             fetchpriority="high"
                             width="800"
                             height="450"
                             style="aspect-ratio: 16/9;">
                    <?php else: ?>
                        <div class="w-100 h-100 d-flex align-items-center justify-content-center text-secondary">
                            <i class="bi bi-graph-up-arrow fs-1"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-5 p-4 p-md-5 d-flex flex-column justify-content-center">
                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 fw-bold">
                            ★ Unggulan Terbaru
                        </span>
                        <a href="<?= Url::to(['/blog/index', 'category' => $featuredPost->category]) ?>" class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 text-decoration-none px-2 py-1">
                            <i class="bi bi-tag-fill me-1"></i><?= Html::encode($featuredPost->category) ?>
                        </a>
                        <span class="badge bg-dark px-2 py-1 fw-bold">
                            <?= Html::encode($featuredPost->symbol) ?> &bull; <?= Html::encode($featuredPost->time_frame) ?>
                        </span>
                        <span class="badge bg-<?= $biasClass ?> px-2 py-1">
                            <?= $bias ?>
                        </span>
                    </div>
                    <h2 class="h4 fw-bold text-dark mb-3">
                        <a href="<?= Url::to(['/blog/view', 'slug' => $featuredPost->slug_id]) ?>" class="text-dark text-decoration-none hover-primary">
                            <?= Html::encode($featuredPost->title_id) ?>
                        </a>
                    </h2>
                    <p class="text-muted small mb-4">
                        <?= Html::encode($featuredPost->excerpt_id ?: strip_tags($featuredPost->content_id)) ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
                        <div class="small text-muted">
                            <i class="bi bi-calendar3 me-1"></i> <?= Html::encode($featuredPost->analysis_date) ?>
                            &bull; <i class="bi bi-clock me-1"></i> <?= $featuredPost->getReadingTime('id') ?> min baca
                        </div>
                        <a href="<?= Url::to(['/blog/view', 'slug' => $featuredPost->slug_id]) ?>" class="btn btn-primary btn-sm rounded-pill px-3">
                            Baca Analisis <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Grid Kartu Artikel -->
    <?php if (empty($models)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
            <div class="py-5">
                <i class="bi bi-journal-x text-muted" style="font-size: 3.5rem;"></i>
                <h4 class="fw-bold mt-3 text-secondary">Belum Ada Artikel yang Sesuai</h4>
                <p class="text-muted">Coba ubah kata kunci pencarian atau reset filter untuk melihat artikel lainnya.</p>
                <a href="<?= Url::to(['/blog/index']) ?>" class="btn btn-outline-primary px-4 mt-2">Tampilkan Semua Artikel</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($models as $idx => $post): ?>
                <?php
                /** @var BlogPost $post */
                $bias = $post->getBias();
                $biasClass = $bias === 'BULLISH' ? 'success' : ($bias === 'BEARISH' ? 'danger' : 'secondary');
                $imgUrl = !empty($post->featured_image) ? Yii::getAlias('@web') . $post->featured_image : null;
                ?>
                <div class="col-md-6 col-lg-4">
                    <article class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden bg-white blog-card transition-all">
                        <a href="<?= Url::to(['/blog/view', 'slug' => $post->slug_id]) ?>" class="text-decoration-none text-dark d-block" style="aspect-ratio: 16/9; overflow: hidden; background: #0f172a;">
                            <?php if ($imgUrl): ?>
                                <img src="<?= Html::encode($imgUrl) ?>"
                                     alt="Chart <?= Html::encode($post->symbol) ?> <?= Html::encode($post->strategy_name) ?>"
                                     class="w-100 h-100 object-fit-cover"
                                     loading="<?= $idx < 3 ? 'eager' : 'lazy' ?>"
                                     width="400"
                                     height="225"
                                     style="aspect-ratio: 16/9;">
                            <?php else: ?>
                                <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-secondary">
                                    <i class="bi bi-bar-chart-line fs-2 mb-1"></i>
                                    <span class="small fw-semibold"><?= Html::encode($post->symbol) ?> Analysis</span>
                                </div>
                            <?php endif; ?>
                        </a>
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                <span class="badge bg-dark fw-bold"><?= Html::encode($post->symbol) ?></span>
                                <span class="badge bg-light text-dark border"><?= Html::encode($post->time_frame) ?></span>
                                <a href="<?= Url::to(['/blog/index', 'category' => $post->category ?: $post->getStrategyLabel()]) ?>" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 text-decoration-none small">
                                    <i class="bi bi-tag-fill me-1"></i><?= Html::encode($post->category ?: $post->getStrategyLabel()) ?>
                                </a>
                                <span class="badge bg-<?= $biasClass ?> ms-auto small"><?= $bias ?></span>
                            </div>
                            <h2 class="h5 fw-bold mb-2" style="font-size: 1.1rem; line-height: 1.4;">
                                <a href="<?= Url::to(['/blog/view', 'slug' => $post->slug_id]) ?>" class="text-dark text-decoration-none hover-primary">
                                    <?= Html::encode($post->title_id) ?>
                                </a>
                            </h2>
                            <p class="text-muted small mb-3 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= Html::encode($post->excerpt_id ?: strip_tags($post->content_id)) ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top small text-muted">
                                <div>
                                    <i class="bi bi-calendar3 me-1"></i> <?= Html::encode($post->analysis_date) ?>
                                </div>
                                <div>
                                    <i class="bi bi-eye me-1"></i> <?= number_format($post->views_count) ?>
                                    &bull; <?= $post->getReadingTime('id') ?> m
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-5 d-flex justify-content-center">
            <?= LinkPager::widget([
                'pagination' => $dataProvider->pagination,
                'options' => ['class' => 'pagination shadow-sm rounded-pill overflow-hidden'],
                'linkContainerOptions' => ['class' => 'page-item'],
                'linkOptions' => ['class' => 'page-link'],
            ]) ?>
        </div>
    <?php endif; ?>
</div>

<style>
.blog-card {
    transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s ease-in-out;
}
.blog-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.08) !important;
}
.hover-primary:hover {
    color: #0d6efd !important;
}
</style>
