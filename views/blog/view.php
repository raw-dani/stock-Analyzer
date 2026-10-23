<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\BlogPost $post */
/** @var string $activeLang 'id' or 'en' */
/** @var array $relatedPosts */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$isEn = ($activeLang === 'en');
$title = $isEn ? $post->title_en : $post->title_id;
$metaDesc = $isEn ? ($post->meta_desc_en ?: $post->excerpt_en) : ($post->meta_desc_id ?: $post->excerpt_id);
$cleanDesc = Html::encode(strip_tags((string) $metaDesc));

$this->title = $title . ' | US Stock Analyzer';
$this->params['breadcrumbs'][] = ['label' => 'Blog Info Saham', 'url' => ['/blog/index']];
$this->params['breadcrumbs'][] = $post->symbol;

$baseUrl = Yii::$app->request->hostInfo;
$urlId = $baseUrl . Url::to(['/blog/view', 'slug' => $post->slug_id]);
$urlEn = $baseUrl . Url::to(['/blog/view', 'slug' => $post->slug_en]);
$canonicalUrl = $isEn ? $urlEn : $urlId;

$imgUrl = !empty($post->featured_image) ? $baseUrl . $post->featured_image : null;
$tradingPlan = $post->getTradingPlanArray();
$bias = $post->getBias();
$biasClass = $bias === 'BULLISH' ? 'success' : ($bias === 'BEARISH' ? 'danger' : 'secondary');

// Register Dynamic SEO Metadata
$this->registerMetaTag(['name' => 'description', 'content' => $cleanDesc]);
$this->registerLinkTag(['rel' => 'canonical', 'href' => $canonicalUrl]);
$this->registerLinkTag(['rel' => 'alternate', 'hreflang' => 'id', 'href' => $urlId]);
$this->registerLinkTag(['rel' => 'alternate', 'hreflang' => 'en', 'href' => $urlEn]);
$this->registerLinkTag(['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => $urlId]);

// Open Graph
$this->registerMetaTag(['property' => 'og:title', 'content' => $title]);
$this->registerMetaTag(['property' => 'og:description', 'content' => $cleanDesc]);
$this->registerMetaTag(['property' => 'og:type', 'content' => 'article']);
$this->registerMetaTag(['property' => 'og:url', 'content' => $canonicalUrl]);
$this->registerMetaTag(['property' => 'article:published_time', 'content' => date('c', $post->created_at)]);
$this->registerMetaTag(['property' => 'article:modified_time', 'content' => date('c', $post->updated_at)]);
if ($imgUrl) {
    $this->registerMetaTag(['property' => 'og:image', 'content' => $imgUrl]);
    $this->registerMetaTag(['property' => 'og:image:width', 'content' => '1200']);
    $this->registerMetaTag(['property' => 'og:image:height', 'content' => '675']);
}

// Twitter Card
$this->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary_large_image']);
$this->registerMetaTag(['name' => 'twitter:title', 'content' => $title]);
$this->registerMetaTag(['name' => 'twitter:description', 'content' => $cleanDesc]);
if ($imgUrl) {
    $this->registerMetaTag(['name' => 'twitter:image', 'content' => $imgUrl]);
}

// Schema.org Structured Data (JSON-LD)
$schemaData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'NewsArticle',
            '@id' => $canonicalUrl . '#article',
            'isPartOf' => ['@id' => $baseUrl . '/#website'],
            'headline' => $title,
            'description' => $cleanDesc,
            'url' => $canonicalUrl,
            'articleSection' => $post->category ?: $post->getStrategyLabel(),
            'datePublished' => date('c', $post->created_at),
            'dateModified' => date('c', $post->updated_at),
            'mainEntityOfPage' => $canonicalUrl,
            'image' => $imgUrl ? [$imgUrl] : [],
            'author' => [
                '@type' => 'Person',
                'name' => $post->author?->username ?? 'Analyst Team',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'US Stock Volume Analyzer',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $baseUrl . '/favicon.ico',
                ],
            ],
            'about' => [
                '@type' => 'FinancialProduct',
                'name' => $post->symbol . ' Stock',
                'tickerSymbol' => $post->symbol,
                'exchange' => $post->stock?->exchange ?? 'US',
            ],
        ],
        [
            '@type' => 'BreadcrumbList',
            '@id' => $canonicalUrl . '#breadcrumb',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => $baseUrl,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Blog Info Saham',
                    'item' => $baseUrl . Url::to(['/blog/index']),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $post->category ?: $post->getStrategyLabel(),
                    'item' => $baseUrl . Url::to(['/blog/index', 'category' => $post->category ?: $post->getStrategyLabel()]),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 4,
                    'name' => $post->symbol,
                    'item' => $canonicalUrl,
                ],
            ],
        ],
    ],
];
$schemaJson = json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$this->registerJs(<<<JS
    // Injeksi Schema.org JSON-LD secara dinamis
    (function() {
        var script = document.createElement('script');
        script.type = 'application/ld+json';
        script.text = {$schemaJson};
        document.head.appendChild(script);
    })();
JS, \yii\web\View::POS_HEAD);

$isAdmin = Yii::$app->user && !Yii::$app->user->isGuest && Yii::$app->user->identity->isAdmin();
?>

<div class="blog-detail-container pb-5" style="max-width: 960px; margin: 0 auto;">
    <!-- Top Action Bar & Language Switcher -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded-4 shadow-sm">
        <a href="<?= Url::to(['/blog/index']) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Blog
        </a>

        <!-- Fast Bilingual Switcher (INP < 50ms) -->
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted fw-semibold me-1"><i class="bi bi-translate me-1"></i> Bahasa / Language:</span>
            <div class="btn-group p-1 bg-light rounded-pill border" role="group" id="lang-switcher">
                <button type="button" 
                        class="btn btn-sm rounded-pill px-3 <?= !$isEn ? 'btn-primary shadow-sm' : 'btn-light text-dark' ?>" 
                        onclick="switchBlogLanguage('id')">
                    🇮🇩 Indonesia
                </button>
                <button type="button" 
                        class="btn btn-sm rounded-pill px-3 <?= $isEn ? 'btn-primary shadow-sm' : 'btn-light text-dark' ?>" 
                        onclick="switchBlogLanguage('en')">
                    🇺🇸 English
                </button>
            </div>
        </div>

        <?php if ($isAdmin): ?>
            <div>
                <a href="<?= Url::to(['/blog/update', 'id' => $post->id]) ?>" class="btn btn-warning btn-sm rounded-pill px-3">
                    <i class="bi bi-pencil-square me-1"></i> Edit Artikel
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Artikel Card Utama -->
    <article class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
        <!-- Header Artikel -->
        <header class="card-body p-4 p-md-5 border-bottom">
            <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                <span class="badge bg-dark fs-6 px-3 py-2 fw-bold"><?= Html::encode($post->symbol) ?></span>
                <span class="badge bg-light text-dark border fs-6 px-2 py-2">TF: <?= Html::encode($post->time_frame) ?></span>
                <a href="<?= Url::to(['/blog/index', 'category' => $post->category ?: $post->getStrategyLabel()]) ?>" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-6 px-3 py-2 text-decoration-none">
                    <i class="bi bi-tag-fill me-1"></i><?= Html::encode($post->category ?: $post->getStrategyLabel()) ?>
                </a>
                <span class="badge bg-<?= $biasClass ?> fs-6 px-3 py-2"><?= $bias ?></span>
            </div>

            <!-- Judul H1 Semantik Bilingual -->
            <h1 class="display-6 fw-bold text-dark mb-3 lang-block lang-id <?= $isEn ? 'd-none' : '' ?>">
                <?= Html::encode($post->title_id) ?>
            </h1>
            <h1 class="display-6 fw-bold text-dark mb-3 lang-block lang-en <?= !$isEn ? 'd-none' : '' ?>">
                <?= Html::encode($post->title_en) ?>
            </h1>

            <!-- Info Bar Penulis, Tanggal, & Waktu Baca -->
            <div class="d-flex flex-wrap align-items-center gap-3 text-muted small mt-3 pt-3 border-top">
                <div>
                    <i class="bi bi-person-circle text-primary me-1"></i>
                    Oleh: <strong><?= Html::encode($post->author?->username ?? 'Analyst Team') ?></strong>
                </div>
                <div>
                    <i class="bi bi-calendar3 me-1"></i> Sesi: <strong><?= Html::encode($post->analysis_date) ?></strong>
                </div>
                <div>
                    <i class="bi bi-clock me-1"></i>
                    <span class="lang-block lang-id <?= $isEn ? 'd-none' : '' ?>"><?= $post->getReadingTime('id') ?> menit baca</span>
                    <span class="lang-block lang-en <?= !$isEn ? 'd-none' : '' ?>"><?= $post->getReadingTime('en') ?> min read</span>
                </div>
                <div class="ms-auto">
                    <i class="bi bi-eye me-1"></i> <?= number_format($post->views_count) ?> pembaca
                </div>
            </div>
        </header>

        <!-- Visual Hero Chart Screenshot (WebP Optimized, Zero CLS) -->
        <?php if (!empty($post->featured_image)): ?>
            <div class="w-100 bg-dark" style="aspect-ratio: 16/9; max-height: 520px; overflow: hidden;">
                <img src="<?= Html::encode(Yii::getAlias('@web') . $post->featured_image) ?>"
                     alt="Chart Analisis Teknikal <?= Html::encode($post->symbol) ?> Timeframe <?= Html::encode($post->time_frame) ?> Strategi <?= Html::encode($post->strategy_name) ?>"
                     class="w-100 h-100 object-fit-contain"
                     loading="eager"
                     fetchpriority="high"
                     width="960"
                     height="540"
                     style="aspect-ratio: 16/9;">
            </div>
        <?php endif; ?>

        <!-- Konten Artikel & Trading Plan -->
        <div class="card-body p-4 p-md-5">
            <!-- Ringkasan Eksekutif -->
            <div class="p-4 rounded-4 mb-4" style="background-color: #f8fafc; border-left: 4px solid #0d6efd;">
                <div class="fw-bold text-primary mb-2">
                    <i class="bi bi-lightning-charge-fill me-1"></i>
                    <span class="lang-block lang-id <?= $isEn ? 'd-none' : '' ?>">Ringkasan Eksekutif (Executive Summary)</span>
                    <span class="lang-block lang-en <?= !$isEn ? 'd-none' : '' ?>">Executive Summary</span>
                </div>
                <p class="mb-0 text-secondary lang-block lang-id <?= $isEn ? 'd-none' : '' ?>">
                    <?= Html::encode($post->excerpt_id ?: strip_tags($post->content_id)) ?>
                </p>
                <p class="mb-0 text-secondary lang-block lang-en <?= !$isEn ? 'd-none' : '' ?>">
                    <?= Html::encode($post->excerpt_en ?: strip_tags($post->content_en)) ?>
                </p>
            </div>

            <!-- Trading Plan Widget Box -->
            <?php if (!empty($tradingPlan)): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-5" style="background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%); border: 1px solid #bae6fd !important;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h5 fw-bold text-dark mb-0">
                                <i class="bi bi-bullseye text-primary me-2"></i>
                                <span class="lang-block lang-id <?= $isEn ? 'd-none' : '' ?>">Kotak Rencana Trading (Trading Plan)</span>
                                <span class="lang-block lang-en <?= !$isEn ? 'd-none' : '' ?>">Actionable Trading Plan Framework</span>
                            </h3>
                            <span class="badge bg-<?= $biasClass ?> px-3 py-2 fw-bold"><?= $bias ?></span>
                        </div>
                        <div class="row g-3 text-center">
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-3 rounded-3 shadow-sm h-100">
                                    <div class="small text-muted fw-semibold">Level Support</div>
                                    <div class="h5 mb-0 fw-bold text-success mt-1">
                                        <?= !empty($tradingPlan['support']) ? '$' . number_format((float) $tradingPlan['support'], 2) : '-' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-3 rounded-3 shadow-sm h-100">
                                    <div class="small text-muted fw-semibold">Stop Loss (Proteksi)</div>
                                    <div class="h5 mb-0 fw-bold text-danger mt-1">
                                        <?= !empty($tradingPlan['stop_loss']) ? '$' . number_format((float) $tradingPlan['stop_loss'], 2) : '-' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-3 rounded-3 shadow-sm h-100">
                                    <div class="small text-muted fw-semibold">Target Resistensi</div>
                                    <div class="h5 mb-0 fw-bold text-primary mt-1">
                                        <?= !empty($tradingPlan['resistance']) ? '$' . number_format((float) $tradingPlan['resistance'], 2) : '-' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-3 rounded-3 shadow-sm h-100">
                                    <div class="small text-muted fw-semibold">Target Harga (TP)</div>
                                    <div class="h5 mb-0 fw-bold text-info mt-1">
                                        <?= !empty($tradingPlan['target_price']) ? '$' . number_format((float) $tradingPlan['target_price'], 2) : '-' ?>
                                    </div>
                                    <?php if (!empty($tradingPlan['risk_reward_ratio'])): ?>
                                        <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                                            R:R <strong><?= Html::encode($tradingPlan['risk_reward_ratio']) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Isi Konten Artikel Lengkap Bilingual -->
            <div class="blog-content-body typography-rich mb-5">
                <div class="lang-block lang-id <?= $isEn ? 'd-none' : '' ?>">
                    <?= $post->content_id ?>
                </div>
                <div class="lang-block lang-en <?= !$isEn ? 'd-none' : '' ?>">
                    <?= $post->content_en ?>
                </div>
            </div>

            <!-- Tags -->
            <?php $tags = $post->getTagsArray(); ?>
            <?php if (!empty($tags)): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 pt-3 border-top mb-4">
                    <span class="small text-muted fw-semibold"><i class="bi bi-tags me-1"></i> Tags:</span>
                    <?php foreach ($tags as $t): ?>
                        <a href="<?= Url::to(['/blog/index', 'tag' => $t]) ?>" class="badge bg-light text-dark border text-decoration-none px-2 py-1">
                            #<?= Html::encode($t) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Call to Action ke Halaman Live Analysis Tool -->
            <div class="p-4 rounded-4 text-white text-center shadow-sm" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
                <h4 class="fw-bold mb-2">Ingin Menganalisis Saham <?= Html::encode($post->symbol) ?> Secara Live?</h4>
                <p class="text-white text-opacity-90 mb-3" style="max-width: 600px; margin: 0 auto;">
                    Buka grafik interaktif, indikator multi-timeframe, dan radar volume langsung di platform Stock Analyzer.
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <a href="<?= Url::to(['/stock/view', 'symbol' => $post->symbol]) ?>" class="btn btn-light fw-bold px-4 py-2">
                        <i class="bi bi-graph-up me-1"></i> Buka Detail Saham <?= Html::encode($post->symbol) ?>
                    </a>
                    <a href="<?= Url::to(['/rsi-double-bottom/detail', 'symbol' => $post->symbol]) ?>" class="btn btn-outline-light px-3 py-2">
                        <i class="bi bi-activity me-1"></i> Radar RSI
                    </a>
                </div>
            </div>
        </div>
    </article>

    <!-- Artikel Terkait (Related Posts) -->
    <?php if (!empty($relatedPosts)): ?>
        <div class="mt-5">
            <h4 class="fw-bold text-dark mb-3"><i class="bi bi-collection-play me-2 text-primary"></i> Analisis Terkait Lainnya</h4>
            <div class="row g-3">
                <?php foreach ($relatedPosts as $rel): ?>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white p-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-dark"><?= Html::encode($rel->symbol) ?></span>
                                <span class="badge bg-light text-dark border"><?= Html::encode($rel->time_frame) ?></span>
                            </div>
                            <h5 class="h6 fw-bold mb-2">
                                <a href="<?= Url::to(['/blog/view', 'slug' => $rel->slug_id]) ?>" class="text-dark text-decoration-none">
                                    <?= Html::encode($rel->title_id) ?>
                                </a>
                            </h5>
                            <div class="small text-muted mt-auto pt-2 border-top">
                                <?= Html::encode($rel->analysis_date) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function switchBlogLanguage(lang) {
    var isEn = (lang === 'en');
    
    // Toggle elemen bahasa
    document.querySelectorAll('.lang-block.lang-id').forEach(function(el) {
        el.classList.toggle('d-none', isEn);
    });
    document.querySelectorAll('.lang-block.lang-en').forEach(function(el) {
        el.classList.toggle('d-none', !isEn);
    });

    // Update styling tombol switcher
    var buttons = document.querySelectorAll('#lang-switcher button');
    if (buttons.length === 2) {
        if (!isEn) {
            buttons[0].className = 'btn btn-sm rounded-pill px-3 btn-primary shadow-sm';
            buttons[1].className = 'btn btn-sm rounded-pill px-3 btn-light text-dark';
        } else {
            buttons[0].className = 'btn btn-sm rounded-pill px-3 btn-light text-dark';
            buttons[1].className = 'btn btn-sm rounded-pill px-3 btn-primary shadow-sm';
        }
    }

    // Update URL slug tanpa reload halaman
    var targetSlug = isEn ? '<?= Html::encode($post->slug_en) ?>' : '<?= Html::encode($post->slug_id) ?>';
    var newUrl = window.location.origin + '/blog/' + targetSlug;
    if (window.history && window.history.replaceState) {
        window.history.replaceState({lang: lang}, '', newUrl);
    }
}
</script>

<style>
.typography-rich {
    font-size: 1.05rem;
    line-height: 1.75;
    color: #334155;
}
.typography-rich h2 {
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
    margin-top: 1.8rem;
    margin-bottom: 0.9rem;
}
.typography-rich h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #1e293b;
    margin-top: 1.4rem;
    margin-bottom: 0.75rem;
}
.typography-rich p {
    margin-bottom: 1.25rem;
}
.typography-rich ul {
    margin-bottom: 1.25rem;
    padding-left: 1.5rem;
}
.typography-rich li {
    margin-bottom: 0.4rem;
}
.typography-rich blockquote {
    background-color: #f8fafc;
    border-left: 4px solid #3b82f6;
    padding: 1rem 1.25rem;
    border-radius: 0.5rem;
    margin: 1.5rem 0;
    font-size: 0.95rem;
    color: #475569;
}
</style>
