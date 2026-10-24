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

$this->title = 'Blog Analisis Saham & Riset Trading Plan AI | US Stock Analyzer';
$this->params['breadcrumbs'][] = 'Blog Info Saham';

// SEO Meta Tags Halaman Indeks Blog
$this->registerMetaTag([
    'name' => 'description',
    'content' => 'Portal riset & analisis teknikal saham Wall Street berbasis kecerdasan buatan (AI), deteksi lonjakan volume (RVOL), formasi candlestick multi-timeframe, dan trading plan disiplin terukur.',
]);
$this->registerMetaTag(['name' => 'keywords', 'content' => 'analisis saham, info saham, stock analysis, trading plan, volume breakout, relative volume, rvol, double bottom, fibonacci, wall street, analisa teknikal']);
$this->registerMetaTag(['property' => 'og:title', 'content' => $this->title]);
$this->registerMetaTag(['property' => 'og:description', 'content' => 'Ulasan teknikal emiten AS & sinyal trading plan kuantitatif dwibahasa berbasis AI.']);
$this->registerMetaTag(['property' => 'og:type', 'content' => 'website']);
$this->registerMetaTag(['property' => 'og:url', 'content' => Url::to(['/blog/index'], true)]);
$this->registerLinkTag(['rel' => 'canonical', 'href' => Url::to(['/blog/index'], true)]);

$models = $dataProvider->getModels();
$isAdmin = Yii::$app->user && !Yii::$app->user->isGuest && Yii::$app->user->identity->isAdmin();

// Kurasi daftar strategi populer untuk Quick-Filter Ticker
$quickStrategies = [
    '' => ['label' => 'Semua Setup', 'icon' => 'bi-grid-fill'],
    'strategy-confluence' => ['label' => 'Confluence Strategy', 'icon' => 'bi-layers-fill'],
    'volume-breakout' => ['label' => 'Volume Breakout', 'icon' => 'bi-lightning-charge-fill'],
    'double-bottom' => ['label' => 'Double Bottom', 'icon' => 'bi-graph-up-arrow'],
    'fibonacci' => ['label' => 'Fibonacci Retracement', 'icon' => 'bi-pie-chart-fill'],
    'candlestick' => ['label' => 'Candlestick Pattern', 'icon' => 'bi-bar-chart-steps'],
    'bullish-pattern' => ['label' => 'Bullish Continuation', 'icon' => 'bi-arrow-up-right-circle-fill'],
];
?>

<div class="blog-index-wrapper pb-5">
    <!-- ====================================================================
         1. HERO SECTION: Luminous, Clean, High-Trust & Credible
         ==================================================================== -->
    <section class="blog-hero-fresh mb-4 p-4 p-md-5 rounded-4 shadow-sm position-relative overflow-hidden">
        <div class="blog-hero-glow"></div>
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-8">
                <!-- Live Intelligence Badge -->
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white border shadow-xs mb-3">
                    <span class="live-signal-dot"></span>
                    <span class="fw-semibold text-slate-700 small" style="color: #334155;">
                        AI Market Intelligence &bull; Riset Kuantitatif Dwibahasa
                    </span>
                </div>

                <h1 class="display-5 fw-bold text-slate-900 mb-3 blog-hero-title">
                    Riset &amp; Trading Plan Saham Pilihan
                </h1>

                <p class="lead text-slate-600 mb-4 blog-hero-subtitle" style="max-width: 680px; color: #475569; font-size: 1.08rem; line-height: 1.6;">
                    Temukan setup trading saham Wall Street berprobabilitas tinggi dengan validasi lonjakan volume institusional (RVOL), analisis multi-timeframe terstruktur, serta batas risiko (TP/SL) yang disiplin dan transparan.
                </p>

                <!-- E-E-A-T Credibility Trust Pillars -->
                <div class="d-flex flex-wrap gap-2 pt-1 blog-trust-pillars">
                    <span class="trust-pill" title="Validasi lonjakan volume di atas rata-rata 20 hari">
                        <i class="bi bi-shield-check text-emerald me-1"></i> Validasi RVOL Institusi
                    </span>
                    <span class="trust-pill" title="Rasio potensi keuntungan terhadap risiko terukur">
                        <i class="bi bi-bullseye text-primary me-1"></i> Risk/Reward Terukur
                    </span>
                    <span class="trust-pill" title="Pemetaan skenario konfirmasi dan titik invalidasi">
                        <i class="bi bi-diagram-3 text-cyan me-1"></i> Dual-Scenario Mapping
                    </span>
                    <span class="trust-pill" title="Dukungan AI kuantitatif berstandar Wall Street">
                        <i class="bi bi-cpu text-indigo me-1"></i> AI Quant Framework
                    </span>
                </div>
            </div>

            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <div class="d-flex flex-column flex-sm-row flex-lg-column gap-2 justify-content-lg-end">
                    <a href="<?= Url::to(['/scanner/index']) ?>" class="btn btn-primary fw-semibold px-4 py-2.5 shadow-sm rounded-pill d-inline-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-search"></i>
                        <span>Buka Real-Time Scanner</span>
                    </a>
                    <?php if ($isAdmin): ?>
                        <a href="<?= Url::to(['/blog/admin']) ?>" class="btn btn-outline-secondary fw-semibold px-4 py-2 rounded-pill d-inline-flex align-items-center justify-content-center gap-2 bg-white">
                            <i class="bi bi-speedometer2 text-warning"></i>
                            <span>Dashboard Admin</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         2. TRENDING SETUP TICKER (Quick Strategy Filter Pills)
         ==================================================================== -->
    <div class="strategy-ticker-bar mb-3 pb-1">
        <div class="d-flex align-items-center gap-2 overflow-auto py-1" style="white-space: nowrap; scrollbar-width: thin;">
            <span class="small fw-bold text-slate-500 text-uppercase me-1 d-none d-sm-inline-flex align-items-center">
                <i class="bi bi-lightning-charge text-amber-500 me-1"></i> Setup:
            </span>
            <?php foreach ($quickStrategies as $stratKey => $meta): ?>
                <?php
                $isActive = ($selectedStrategy === $stratKey);
                $urlParams = ['/blog/index'];
                if (!empty($search)) $urlParams['q'] = $search;
                if (!empty($selectedCategory)) $urlParams['category'] = $selectedCategory;
                if (!empty($stratKey)) $urlParams['strategy'] = $stratKey;
                ?>
                <a href="<?= Url::to($urlParams) ?>" 
                   class="strategy-pill <?= $isActive ? 'active' : '' ?>">
                    <i class="bi <?= $meta['icon'] ?> me-1.5 opacity-75"></i>
                    <span><?= Html::encode($meta['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ====================================================================
         3. SEARCH & ADVANCED FILTER BAR
         ==================================================================== -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 filter-container-card">
        <div class="card-body p-3">
            <form method="get" action="<?= Url::to(['/blog/index']) ?>" class="row g-2 align-items-center">
                <?php if (!empty($selectedCategory)): ?>
                    <input type="hidden" name="category" value="<?= Html::encode($selectedCategory) ?>">
                <?php endif; ?>
                <div class="col-lg-6 col-md-5">
                    <div class="input-group search-input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-slate-400 ps-3">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="q" value="<?= Html::encode($search) ?>" 
                               class="form-control border-start-0 ps-1" 
                               placeholder="Cari simbol saham (NVDA, TSLA), emiten, atau analisa...">
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <select name="strategy" class="form-select strategy-select" onchange="this.form.submit()">
                        <option value="">Semua Setup Strategi</option>
                        <?php foreach ($strategies as $strat): ?>
                            <option value="<?= Html::encode($strat) ?>" <?= $selectedStrategy === $strat ? 'selected' : '' ?>>
                                <?= Html::encode(BlogPost::strategyToCategory((string) $strat)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1 rounded-3 fw-semibold">
                        Filter
                    </button>
                    <?php if (!empty($search) || !empty($selectedStrategy) || !empty($selectedCategory) || !empty($selectedTag)): ?>
                        <a href="<?= Url::to(['/blog/index']) ?>" class="btn btn-outline-secondary rounded-3" title="Reset Semua Filter">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Active Tag / Search Indicators -->
            <?php if (!empty($selectedTag) || !empty($search) || !empty($selectedCategory) || !empty($selectedStrategy)): ?>
                <div class="mt-2.5 pt-2 border-top d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-slate-500 fw-medium">Filter Aktif:</span>
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-slate-100 text-slate-700 border px-2.5 py-1.5 rounded-pill">
                            Pencarian: "<?= Html::encode($search) ?>"
                            <a href="<?= Url::to(['/blog/index', 'category' => $selectedCategory, 'strategy' => $selectedStrategy, 'tag' => $selectedTag]) ?>" class="text-slate-400 hover-text-dark text-decoration-none ms-1">&times;</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($selectedCategory)): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1.5 rounded-pill">
                            Kategori: <?= Html::encode($selectedCategory) ?>
                            <a href="<?= Url::to(['/blog/index', 'q' => $search, 'strategy' => $selectedStrategy, 'tag' => $selectedTag]) ?>" class="text-primary text-decoration-none ms-1">&times;</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($selectedStrategy)): ?>
                        <span class="badge bg-indigo-50 text-indigo-700 border border-indigo-200 px-2.5 py-1.5 rounded-pill" style="background-color: #eef2ff; color: #4338ca;">
                            Setup: <?= Html::encode(BlogPost::strategyToCategory($selectedStrategy)) ?>
                            <a href="<?= Url::to(['/blog/index', 'q' => $search, 'category' => $selectedCategory, 'tag' => $selectedTag]) ?>" class="text-indigo-700 text-decoration-none ms-1">&times;</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($selectedTag)): ?>
                        <span class="badge bg-slate-100 text-slate-800 border px-2.5 py-1.5 rounded-pill">
                            #<?= Html::encode($selectedTag) ?>
                            <a href="<?= Url::to(['/blog/index', 'q' => $search, 'category' => $selectedCategory, 'strategy' => $selectedStrategy]) ?>" class="text-slate-500 text-decoration-none ms-1">&times;</a>
                        </span>
                    <?php endif; ?>
                    <a href="<?= Url::to(['/blog/index']) ?>" class="small text-danger text-decoration-none ms-auto">
                        <i class="bi bi-trash3 me-1"></i>Reset
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ====================================================================
         4. FEATURED COVER STORY (Spotlight Magazine Header Card)
         ==================================================================== -->
    <?php if ($featuredPost !== null && empty($search) && empty($selectedStrategy) && empty($selectedCategory) && empty($selectedTag)): ?>
        <?php
        $fBias = $featuredPost->getBias();
        $fIsBullish = ($fBias === 'BULLISH');
        $fBiasClass = $fIsBullish ? 'bias-bullish' : ($fBias === 'BEARISH' ? 'bias-bearish' : 'bias-neutral');
        $fImgUrl = !empty($featuredPost->featured_image) ? Yii::getAlias('@web') . $featuredPost->featured_image : null;
        $fPlan = $featuredPost->getTradingPlanArray();
        $fCategory = $featuredPost->category ?: $featuredPost->getStrategyLabel();
        ?>
        <section class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 blog-featured-spotlight bg-white">
            <div class="row g-0">
                <!-- Thumbnail / Chart View -->
                <div class="col-lg-7 position-relative spotlight-img-wrap">
                    <a href="<?= Url::to(['/blog/view', 'slug' => $featuredPost->slug_id]) ?>" class="d-block w-100 h-100 text-decoration-none overflow-hidden">
                        <?php if ($fImgUrl): ?>
                            <img src="<?= Html::encode($fImgUrl) ?>"
                                 alt="Analisis Chart Saham <?= Html::encode($featuredPost->symbol) ?>"
                                 class="w-100 h-100 object-fit-cover spotlight-img"
                                 loading="eager"
                                 fetchpriority="high"
                                 width="800"
                                 height="450"
                                 style="aspect-ratio: 16/9; min-height: 320px;">
                        <?php else: ?>
                            <div class="spotlight-placeholder d-flex flex-column align-items-center justify-content-center w-100 h-100 p-4 text-center">
                                <div class="spotlight-placeholder-icon mb-2">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <span class="h4 fw-bold text-white mb-1"><?= Html::encode($featuredPost->symbol) ?> &bull; <?= Html::encode($featuredPost->time_frame) ?></span>
                                <span class="badge bg-white bg-opacity-20 text-white border border-white border-opacity-25 px-3 py-1">
                                    <?= Html::encode($fCategory) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </a>

                    <!-- Top Left Overlay Badges -->
                    <div class="position-absolute top-0 start-0 m-3 d-flex align-items-center gap-2">
                        <span class="spotlight-badge-gold">
                            <i class="bi bi-star-fill me-1"></i> Riset Utama
                        </span>
                        <span class="badge <?= $fBiasClass ?> px-2.5 py-1.5 shadow-xs fw-bold">
                            <i class="bi <?= $fIsBullish ? 'bi-arrow-up-right' : 'bi-arrow-down-right' ?> me-1"></i><?= $fBias ?>
                        </span>
                    </div>

                    <!-- Bottom Overlay Ticker Pill -->
                    <div class="position-absolute bottom-0 start-0 m-3">
                        <span class="badge bg-dark bg-opacity-80 backdrop-blur text-white px-3 py-1.5 rounded-pill shadow-sm fw-bold">
                            <?= Html::encode($featuredPost->symbol) ?> &bull; <?= Html::encode($featuredPost->time_frame) ?>
                        </span>
                    </div>
                </div>

                <!-- Spotlight Content Column -->
                <div class="col-lg-5 p-4 p-md-5 d-flex flex-column justify-content-between spotlight-body">
                    <div>
                        <!-- Category & Timeframe Meta -->
                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                            <a href="<?= Url::to(['/blog/index', 'category' => $fCategory]) ?>" 
                               class="badge badge-soft-primary text-decoration-none">
                                <i class="bi bi-tag-fill me-1"></i><?= Html::encode($fCategory) ?>
                            </a>
                            <span class="small text-slate-500">
                                <i class="bi bi-calendar3 me-1"></i> <?= Html::encode($featuredPost->analysis_date) ?>
                            </span>
                            <span class="small text-slate-500 ms-auto">
                                <i class="bi bi-clock me-1"></i> <?= $featuredPost->getReadingTime('id') ?> min baca
                            </span>
                        </div>

                        <!-- Main Title -->
                        <h2 class="h3 fw-bold text-slate-900 mb-3 spotlight-title">
                            <a href="<?= Url::to(['/blog/view', 'slug' => $featuredPost->slug_id]) ?>" class="text-decoration-none spotlight-title-link">
                                <?= Html::encode($featuredPost->title_id) ?>
                            </a>
                        </h2>

                        <!-- Excerpt -->
                        <p class="text-slate-600 mb-4 spotlight-excerpt" style="line-height: 1.65; color: #475569;">
                            <?= Html::encode($featuredPost->excerpt_id ?: strip_tags($featuredPost->content_id)) ?>
                        </p>

                        <!-- Actionable Trading Metrics Box (If available) -->
                        <?php if (!empty($fPlan['target_price']) || !empty($fPlan['stop_loss']) || !empty($fPlan['risk_reward_ratio'])): ?>
                            <div class="spotlight-metrics-box p-3 rounded-3 mb-4">
                                <div class="row g-2 text-center">
                                    <div class="col-4">
                                        <div class="small text-slate-500 fw-semibold text-uppercase" style="font-size: 0.72rem;">Target (TP)</div>
                                        <div class="fw-bold text-emerald fs-6 mt-0.5">
                                            <?= !empty($fPlan['target_price']) ? '$' . number_format((float) $fPlan['target_price'], 2) : '-' ?>
                                        </div>
                                    </div>
                                    <div class="col-4 border-start border-end">
                                        <div class="small text-slate-500 fw-semibold text-uppercase" style="font-size: 0.72rem;">Stop Loss (SL)</div>
                                        <div class="fw-bold text-rose fs-6 mt-0.5">
                                            <?= !empty($fPlan['stop_loss']) ? '$' . number_format((float) $fPlan['stop_loss'], 2) : '-' ?>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-slate-500 fw-semibold text-uppercase" style="font-size: 0.72rem;">Risk / Reward</div>
                                        <div class="fw-bold text-indigo fs-6 mt-0.5">
                                            <?= !empty($fPlan['risk_reward_ratio']) ? Html::encode($fPlan['risk_reward_ratio']) : '-' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Author & CTA Button -->
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top mt-auto">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle-sm bg-primary bg-opacity-10 text-primary fw-bold">
                                <i class="bi bi-person-check-fill"></i>
                            </div>
                            <div class="small">
                                <div class="fw-bold text-slate-800" style="color: #1e293b;"><?= Html::encode($featuredPost->author?->username ?? 'Tim Riset') ?></div>
                                <div class="text-slate-400" style="font-size: 0.75rem;">Analis Kuantitatif</div>
                            </div>
                        </div>

                        <a href="<?= Url::to(['/blog/view', 'slug' => $featuredPost->slug_id]) ?>" 
                           class="btn btn-primary rounded-pill px-4 py-2 fw-semibold d-inline-flex align-items-center gap-1.5 spotlight-btn shadow-sm">
                            <span>Baca Trading Plan</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ====================================================================
         5. ARTICLES SECTION TITLE
         ==================================================================== -->
    <div class="d-flex align-items-center justify-content-between mb-3.5">
        <div>
            <h2 class="h4 fw-bold text-slate-900 mb-1" style="color: #0f172a;">
                <?php if (!empty($selectedStrategy)): ?>
                    Setup <?= Html::encode(BlogPost::strategyToCategory($selectedStrategy)) ?>
                <?php elseif (!empty($selectedCategory)): ?>
                    Kategori: <?= Html::encode($selectedCategory) ?>
                <?php elseif (!empty($search)): ?>
                    Hasil Pencarian "<?= Html::encode($search) ?>"
                <?php else: ?>
                    Semua Analisis &amp; Trading Plan Terkini
                <?php endif; ?>
            </h2>
            <p class="small text-slate-500 mb-0">
                Menampilkan <?= number_format($dataProvider->getTotalCount()) ?> artikel riset berbasis volume dan strategi teknikal.
            </p>
        </div>
    </div>

    <!-- ====================================================================
         6. ARTICLE CARDS GRID
         ==================================================================== -->
    <?php if (empty($models)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white my-4">
            <div class="py-5">
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-slate-100 text-slate-400" style="width: 72px; height: 72px; font-size: 2rem;">
                        <i class="bi bi-search"></i>
                    </span>
                </div>
                <h3 class="h5 fw-bold text-slate-800 mb-2">Belum Ada Analisis yang Sesuai</h3>
                <p class="text-slate-500 mb-4" style="max-width: 460px; margin: 0 auto;">
                    Tidak ditemukan artikel dengan kriteria filter saat ini. Coba ubah kata kunci pencarian atau reset filter ke awal.
                </p>
                <a href="<?= Url::to(['/blog/index']) ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Tampilkan Semua Analisis
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-5">
            <?php foreach ($models as $idx => $post): ?>
                <?php
                /** @var BlogPost $post */
                $bias = $post->getBias();
                $isBullish = ($bias === 'BULLISH');
                $biasClass = $isBullish ? 'bias-bullish' : ($bias === 'BEARISH' ? 'bias-bearish' : 'bias-neutral');
                $imgUrl = !empty($post->featured_image) ? Yii::getAlias('@web') . $post->featured_image : null;
                $tradingPlan = $post->getTradingPlanArray();
                $categoryLabel = $post->category ?: $post->getStrategyLabel();
                ?>
                <div class="col-md-6 col-lg-4">
                    <article class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden bg-white blog-card d-flex flex-column">
                        <!-- Card Media / Thumbnail -->
                        <div class="position-relative overflow-hidden card-media-wrap">
                            <a href="<?= Url::to(['/blog/view', 'slug' => $post->slug_id]) ?>" 
                               class="d-block w-100 text-decoration-none position-relative" 
                               style="aspect-ratio: 16/9; overflow: hidden; background: #0b1120;">
                                <?php if ($imgUrl): ?>
                                    <img src="<?= Html::encode($imgUrl) ?>"
                                         alt="Chart Saham <?= Html::encode($post->symbol) ?> <?= Html::encode($post->strategy_name) ?>"
                                         class="w-100 h-100 object-fit-cover card-img-zoom"
                                         loading="<?= $idx < 3 ? 'eager' : 'lazy' ?>"
                                         width="400"
                                         height="225"
                                         style="aspect-ratio: 16/9;">
                                <?php else: ?>
                                    <!-- Dynamic Fintech Graphic Fallback -->
                                    <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-center p-3 card-media-fallback">
                                        <div class="fallback-icon-ring mb-2">
                                            <i class="bi bi-bar-chart-line-fill text-white"></i>
                                        </div>
                                        <div class="fw-bold text-white fs-6 mb-0"><?= Html::encode($post->symbol) ?> &bull; <?= Html::encode($post->time_frame) ?></div>
                                        <div class="small text-slate-400" style="font-size: 0.75rem;"><?= Html::encode($categoryLabel) ?></div>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <!-- Floating Badges on Image -->
                            <div class="position-absolute top-0 start-0 m-2.5 d-flex align-items-center gap-1.5">
                                <span class="badge bg-dark bg-opacity-85 backdrop-blur text-white px-2.5 py-1 rounded-pill small fw-bold">
                                    <?= Html::encode($post->symbol) ?> &bull; <?= Html::encode($post->time_frame) ?>
                                </span>
                            </div>
                            <div class="position-absolute top-0 end-0 m-2.5">
                                <span class="badge <?= $biasClass ?> px-2.5 py-1 rounded-pill small fw-bold shadow-xs">
                                    <i class="bi <?= $isBullish ? 'bi-arrow-up-right' : 'bi-arrow-down-right' ?> me-0.5"></i><?= $bias ?>
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body p-4 d-flex flex-column flex-grow-1">
                            <!-- Category Pill -->
                            <div class="mb-2">
                                <a href="<?= Url::to(['/blog/index', 'category' => $categoryLabel]) ?>" 
                                   class="badge badge-soft-primary text-decoration-none">
                                    <i class="bi bi-tag-fill me-1"></i><?= Html::encode($categoryLabel) ?>
                                </a>
                            </div>

                            <!-- Post Title -->
                            <h3 class="h5 fw-bold mb-2 card-post-title" style="font-size: 1.12rem; line-height: 1.42;">
                                <a href="<?= Url::to(['/blog/view', 'slug' => $post->slug_id]) ?>" class="text-slate-900 text-decoration-none hover-primary">
                                    <?= Html::encode($post->title_id) ?>
                                </a>
                            </h3>

                            <!-- Excerpt clamped to 2 lines -->
                            <p class="text-slate-600 small mb-3 flex-grow-1 card-post-excerpt" style="color: #64748b; line-height: 1.55;">
                                <?= Html::encode($post->excerpt_id ?: strip_tags($post->content_id)) ?>
                            </p>

                            <!-- Micro-Trading Plan Metric Strip (If TP/SL exist) -->
                            <?php if (!empty($tradingPlan['target_price']) || !empty($tradingPlan['stop_loss']) || !empty($tradingPlan['risk_reward_ratio'])): ?>
                                <div class="trading-micro-strip p-2 rounded-3 bg-slate-50 border mb-3">
                                    <div class="d-flex align-items-center justify-content-between text-center">
                                        <div class="px-1 text-start">
                                            <span class="small text-slate-500 d-block" style="font-size: 0.7rem;">TARGET</span>
                                            <span class="fw-bold text-emerald" style="font-size: 0.85rem;">
                                                <?= !empty($tradingPlan['target_price']) ? '$' . number_format((float) $tradingPlan['target_price'], 2) : '-' ?>
                                            </span>
                                        </div>
                                        <div class="px-1 border-start border-end">
                                            <span class="small text-slate-500 d-block" style="font-size: 0.7rem;">STOP LOSS</span>
                                            <span class="fw-bold text-rose" style="font-size: 0.85rem;">
                                                <?= !empty($tradingPlan['stop_loss']) ? '$' . number_format((float) $tradingPlan['stop_loss'], 2) : '-' ?>
                                            </span>
                                        </div>
                                        <div class="px-1 text-end">
                                            <span class="small text-slate-500 d-block" style="font-size: 0.7rem;">R:R</span>
                                            <span class="fw-bold text-indigo" style="font-size: 0.85rem;">
                                                <?= !empty($tradingPlan['risk_reward_ratio']) ? Html::encode($tradingPlan['risk_reward_ratio']) : '-' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Card Footer: Meta & Link -->
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto small text-slate-500">
                                <div class="d-flex align-items-center gap-2">
                                    <span><i class="bi bi-calendar3 me-1"></i> <?= Html::encode($post->analysis_date) ?></span>
                                    <span>&bull;</span>
                                    <span><i class="bi bi-eye me-1"></i> <?= number_format($post->views_count) ?></span>
                                </div>
                                <div>
                                    <a href="<?= Url::to(['/blog/view', 'slug' => $post->slug_id]) ?>" class="fw-bold text-primary text-decoration-none read-more-link">
                                        Rencana <i class="bi bi-arrow-right ms-0.5"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ====================================================================
             7. BOTTOM SCANNER ENGAGEMENT CTA BANNER
             ==================================================================== -->
        <section class="scanner-engagement-banner p-4 p-md-5 rounded-4 shadow-sm mb-5 position-relative overflow-hidden">
            <div class="row align-items-center position-relative" style="z-index: 2;">
                <div class="col-lg-8">
                    <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1 rounded-pill bg-white border shadow-xs mb-2 text-emerald fw-semibold small">
                        <i class="bi bi-lightning-charge-fill"></i> Deteksi Volume Real-Time
                    </div>
                    <h3 class="h4 fw-bold text-slate-900 mb-2">Ingin Memindai Saham dengan Setup Serupa Lebih Awal?</h3>
                    <p class="text-slate-600 mb-0" style="max-width: 620px;">
                        Gunakan Volume Breakout Scanner untuk memfilter 8.000+ saham Wall Street dengan konfirmasi lonjakan volume institusional (RVOL &gt; 1.5x) sebelum lonjakan harga terjadi.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="<?= Url::to(['/scanner/index']) ?>" class="btn btn-emerald fw-bold px-4 py-2.5 rounded-pill shadow-sm d-inline-flex align-items-center gap-2">
                        <span>Buka Scanner Gratis</span>
                        <i class="bi bi-arrow-up-right"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- ====================================================================
             8. PAGINATION
             ==================================================================== -->
        <div class="mt-4 mb-5 d-flex justify-content-center">
            <?= LinkPager::widget([
                'pagination' => $dataProvider->pagination,
                'options' => ['class' => 'pagination shadow-sm rounded-pill overflow-hidden bg-white p-1 border'],
                'linkContainerOptions' => ['class' => 'page-item'],
                'linkOptions' => ['class' => 'page-link border-0 rounded-pill px-3 py-1.5 fw-semibold'],
            ]) ?>
        </div>
    <?php endif; ?>
</div>

<!-- ====================================================================
     DESIGN SYSTEM & CLEAN FRESH CSS STYLING
     ==================================================================== -->
<style>
/* Base Colors & Helpers */
.text-slate-900 { color: #0f172a !important; }
.text-slate-800 { color: #1e293b !important; }
.text-slate-700 { color: #334155 !important; }
.text-slate-600 { color: #475569 !important; }
.text-slate-500 { color: #64748b !important; }
.text-slate-400 { color: #94a3b8 !important; }
.bg-slate-50 { background-color: #f8fafc !important; }
.bg-slate-100 { background-color: #f1f5f9 !important; }

.text-emerald { color: #059669 !important; }
.text-rose { color: #e11d48 !important; }
.text-indigo { color: #4f46e5 !important; }
.text-cyan { color: #0891b2 !important; }

/* 1. Hero Fresh Banner */
.blog-hero-fresh {
    background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 50%, #f8fafc 100%);
    border: 1px solid rgba(226, 232, 240, 0.9);
    position: relative;
}
.blog-hero-fresh::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #4f46e5 0%, #06b6d4 50%, #10b981 100%);
}
.blog-hero-glow {
    position: absolute;
    top: -40px;
    right: -40px;
    width: 320px;
    height: 320px;
    background: radial-gradient(circle, rgba(79, 70, 229, 0.08) 0%, rgba(6, 182, 212, 0.04) 50%, transparent 70%);
    pointer-events: none;
    z-index: 1;
}
.blog-hero-title {
    letter-spacing: -0.025em;
    line-height: 1.2;
}

/* Pulsing Live Dot */
.live-signal-dot {
    width: 8px;
    height: 8px;
    background-color: #10b981;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: livePulse 2s infinite;
}
@keyframes livePulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Trust Pillars */
.blog-trust-pillars {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.trust-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.75rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #334155;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

/* 2. Strategy Ticker Bar */
.strategy-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 9999px;
    color: #475569;
    font-size: 0.84rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}
.strategy-pill:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}
.strategy-pill.active {
    background: #4f46e5;
    color: #ffffff !important;
    border-color: #4f46e5;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
}

/* 3. Filter Bar */
.filter-container-card {
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
}
.search-input-group .form-control {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #0f172a;
    font-size: 0.9rem;
}
.search-input-group .form-control:focus {
    background: #ffffff;
    box-shadow: none;
    border-color: #4f46e5;
}
.strategy-select {
    background-color: #f8fafc;
    border-color: #e2e8f0;
    color: #1e293b;
    font-size: 0.9rem;
}

/* 4. Directional Bias Badges */
.bias-bullish {
    background-color: #ecfdf5 !important;
    color: #059669 !important;
    border: 1px solid #a7f3d0 !important;
}
.bias-bearish {
    background-color: #fff1f2 !important;
    color: #e11d48 !important;
    border: 1px solid #fecdd3 !important;
}
.bias-neutral {
    background-color: #f1f5f9 !important;
    color: #475569 !important;
    border: 1px solid #e2e8f0 !important;
}

/* 5. Spotlight Magazine Cover Card */
.blog-featured-spotlight {
    border: 1px solid rgba(226, 232, 240, 0.9) !important;
    transition: all 0.25s ease-in-out;
}
.blog-featured-spotlight:hover {
    box-shadow: 0 16px 32px -4px rgba(15, 23, 42, 0.08) !important;
}
.spotlight-img-wrap {
    min-height: 320px;
    background: #0b1120;
}
.spotlight-img {
    transition: transform 0.4s ease;
}
.blog-featured-spotlight:hover .spotlight-img {
    transform: scale(1.02);
}
.spotlight-placeholder {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
}
.spotlight-placeholder-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: #38bdf8;
}
.spotlight-badge-gold {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 700;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}
.spotlight-title-link {
    color: #0f172a;
    transition: color 0.18s ease;
}
.spotlight-title-link:hover {
    color: #4f46e5 !important;
}
.spotlight-metrics-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.avatar-circle-sm {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}
.spotlight-btn {
    transition: all 0.2s ease;
}
.spotlight-btn:hover {
    transform: translateX(2px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3) !important;
}

/* 6. Grid Blog Cards */
.blog-card {
    border: 1px solid rgba(226, 232, 240, 0.85) !important;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease;
}
.blog-card:hover {
    transform: translateY(-5px);
    border-color: #cbd5e1 !important;
    box-shadow: 0 16px 30px -6px rgba(15, 23, 42, 0.09) !important;
}
.card-media-wrap {
    background-color: #0b1120;
}
.card-img-zoom {
    transition: transform 0.35s ease;
}
.blog-card:hover .card-img-zoom {
    transform: scale(1.04);
}
.card-media-fallback {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
}
.fallback-icon-ring {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.card-post-title {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.card-post-excerpt {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.hover-primary {
    transition: color 0.15s ease;
}
.hover-primary:hover {
    color: #4f46e5 !important;
}
.badge-soft-primary {
    background-color: #eef2ff !important;
    color: #4f46e5 !important;
    border: 1px solid #e0e7ff;
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.3rem 0.65rem;
    border-radius: 9999px;
}
.read-more-link {
    transition: transform 0.15s ease;
    display: inline-flex;
    align-items: center;
}
.blog-card:hover .read-more-link {
    transform: translateX(3px);
}

/* 7. Bottom Scanner Engagement Banner */
.scanner-engagement-banner {
    background: linear-gradient(135deg, #ecfdf5 0%, #eff6ff 100%);
    border: 1px solid rgba(167, 243, 208, 0.6);
}
.btn-emerald {
    background-color: #059669;
    color: #ffffff;
    border: none;
    transition: all 0.2s ease;
}
.btn-emerald:hover {
    background-color: #047857;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(5, 150, 105, 0.25) !important;
}

/* Backdrop blur helper */
.backdrop-blur {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}
.shadow-xs {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
</style>
