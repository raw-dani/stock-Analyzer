<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\BlogPost $model */
/** @var bool $isNew */

use app\models\BlogPost;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = ($isNew ? 'Buat Artikel Baru' : 'Edit Artikel: ' . $model->symbol) . ' | Admin Blog';
$this->params['breadcrumbs'][] = ['label' => 'Blog Info Saham', 'url' => ['/blog/index']];
$this->params['breadcrumbs'][] = ['label' => 'Manajemen Admin', 'url' => ['/blog/admin']];
$this->params['breadcrumbs'][] = $isNew ? 'Buat Baru' : 'Edit';

$tradingPlan = $model->getTradingPlanArray();
?>

<div class="blog-form-container pb-5" style="max-width: 1040px; margin: 0 auto;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <?= $isNew ? '✍️ Buat Artikel Analisis Baru' : '✏️ Edit Artikel: ' . Html::encode($model->symbol) ?>
            </h1>
            <p class="text-muted small mb-0">Lengkapi atau sesuaikan narasi analisis teknikal dwibahasa dan trading plan.</p>
        </div>
        <a href="<?= Url::to(['/blog/admin']) ?>" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>

    <?php $form = ActiveForm::begin(['id' => 'blog-post-form']); ?>

    <div class="row g-4">
        <!-- Kolom Kiri: Konten Utama Dwibahasa -->
        <div class="col-lg-8">
            <!-- Nav Tabs Bahasa -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
                <div class="card-header bg-light border-bottom p-3">
                    <ul class="nav nav-pills" id="langTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill px-3 py-1 fw-bold" id="id-tab" data-bs-toggle="tab" data-bs-target="#tab-id" type="button" role="tab">
                                🇮🇩 Versi Bahasa Indonesia
                            </button>
                        </li>
                        <li class="nav-item ms-2" role="presentation">
                            <button class="nav-link rounded-pill px-3 py-1 fw-bold" id="en-tab" data-bs-toggle="tab" data-bs-target="#tab-en" type="button" role="tab">
                                🇺🇸 Versi English
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-4">
                    <div class="tab-content" id="langTabContent">
                        <!-- Tab Indonesia -->
                        <div class="tab-pane fade show active" id="tab-id" role="tabpanel">
                            <?= $form->field($model, 'title_id')->textInput(['maxlength' => true, 'placeholder' => 'Judul artikel menarik dalam Bahasa Indonesia'])->label('Judul Artikel (ID)') ?>

                            <?= $form->field($model, 'excerpt_id')->textarea(['rows' => 3, 'placeholder' => 'Ringkasan eksekutif padat 2-3 kalimat'])->label('Ringkasan Eksekutif / Excerpt (ID)') ?>

                            <?= $form->field($model, 'content_id')->textarea(['rows' => 14, 'placeholder' => 'Isi analisis teknikal lengkap dalam HTML semantik (<h2>, <h3>, <p>, <ul>, <li>)'])->label('Konten Lengkap Analisis (ID)') ?>

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-search me-1"></i> Optimasi SEO (Bahasa Indonesia)</h6>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <?= $form->field($model, 'meta_title_id')->textInput(['maxlength' => true, 'placeholder' => 'Meta Title (50-60 karakter)'])->label('SEO Meta Title (ID)') ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?= $form->field($model, 'meta_desc_id')->textInput(['maxlength' => true, 'placeholder' => 'Meta Description (140-160 karakter)'])->label('SEO Meta Description (ID)') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab English -->
                        <div class="tab-pane fade" id="tab-en" role="tabpanel">
                            <?= $form->field($model, 'title_en')->textInput(['maxlength' => true, 'placeholder' => 'Engaging SEO Title in English'])->label('Article Title (EN)') ?>

                            <?= $form->field($model, 'excerpt_en')->textarea(['rows' => 3, 'placeholder' => 'Concise executive summary in English'])->label('Executive Summary / Excerpt (EN)') ?>

                            <?= $form->field($model, 'content_en')->textarea(['rows' => 14, 'placeholder' => 'Complete technical analysis content in semantic HTML (<h2>, <h3>, <p>, <ul>, <li>)'])->label('Full Analysis Content (EN)') ?>

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-search me-1"></i> SEO Optimization (English)</h6>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <?= $form->field($model, 'meta_title_en')->textInput(['maxlength' => true, 'placeholder' => 'SEO Meta Title (50-60 chars)'])->label('SEO Meta Title (EN)') ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?= $form->field($model, 'meta_desc_en')->textInput(['maxlength' => true, 'placeholder' => 'SEO Meta Description (140-160 chars)'])->label('SEO Meta Description (EN)') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trading Plan Section -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-bullseye text-primary me-2"></i> Parameter Trading Plan</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Bias Pasar</label>
                            <select name="BlogPost[trading_plan][bias]" class="form-select">
                                <option value="BULLISH" <?= ($tradingPlan['bias'] ?? '') === 'BULLISH' ? 'selected' : '' ?>>BULLISH</option>
                                <option value="BEARISH" <?= ($tradingPlan['bias'] ?? '') === 'BEARISH' ? 'selected' : '' ?>>BEARISH</option>
                                <option value="NEUTRAL" <?= ($tradingPlan['bias'] ?? '') === 'NEUTRAL' ? 'selected' : '' ?>>NEUTRAL</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Level Support ($)</label>
                            <input type="number" step="0.01" name="BlogPost[trading_plan][support]" value="<?= Html::encode((string) ($tradingPlan['support'] ?? '')) ?>" class="form-control" placeholder="105.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Stop Loss ($)</label>
                            <input type="number" step="0.01" name="BlogPost[trading_plan][stop_loss]" value="<?= Html::encode((string) ($tradingPlan['stop_loss'] ?? '')) ?>" class="form-control" placeholder="98.50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Target Resistensi ($)</label>
                            <input type="number" step="0.01" name="BlogPost[trading_plan][resistance]" value="<?= Html::encode((string) ($tradingPlan['resistance'] ?? '')) ?>" class="form-control" placeholder="120.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Target Harga / TP ($)</label>
                            <input type="number" step="0.01" name="BlogPost[trading_plan][target_price]" value="<?= Html::encode((string) ($tradingPlan['target_price'] ?? '')) ?>" class="form-control" placeholder="128.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Risk : Reward Ratio</label>
                            <input type="text" name="BlogPost[trading_plan][risk_reward_ratio]" value="<?= Html::encode((string) ($tradingPlan['risk_reward_ratio'] ?? '1:2.5')) ?>" class="form-control" placeholder="1:2.5">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Parameter Granular, Gambar, & Status -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-sliders text-secondary me-2"></i> Metadata Granular</h5>
                </div>
                <div class="card-body p-4">
                    <?= $form->field($model, 'symbol')->textInput(['maxlength' => true, 'placeholder' => 'cth: AAPL, CMCSA', 'style' => 'text-transform:uppercase; font-weight:bold;'])->label('Simbol Saham') ?>

                    <?= $form->field($model, 'time_frame')->dropDownList([
                        '1H' => '1 Jam (1H)',
                        '2H' => '2 Jam (2H)',
                        '4H' => '4 Jam (4H)',
                        '1D' => 'Harian (1D)',
                        '1W' => 'Mingguan (1W)',
                    ])->label('Time Frame') ?>

                    <?= $form->field($model, 'strategy_name')->dropDownList([
                        'general' => 'General Analysis',
                        'double-bottom' => 'Double Bottom',
                        'triple-bottom' => 'Triple Bottom',
                        'rsi-double-bottom' => 'RSI Double Bottom',
                        'moving-average' => 'Moving Average Confluence',
                        'bollinger-bands' => 'Bollinger Bands Breakout',
                        'bullish-pattern' => 'Bullish Pattern',
                        'fibonacci' => 'Fibonacci Retracement',
                        'volume-breakout' => 'Volume Breakout',
                    ])->label('Strategi / Setup Pola') ?>

                    <?= $form->field($model, 'category')->textInput(['placeholder' => 'cth: RSI Double Bottom, Moving Average'])->label('Kategori Artikel') ?>

                    <?= $form->field($model, 'analysis_date')->textInput(['type' => 'date'])->label('Tanggal Sesi Analisis') ?>

                    <?= $form->field($model, 'status')->dropDownList(BlogPost::STATUSES)->label('Status Publikasi') ?>

                    <?= $form->field($model, 'tags')->textInput(['placeholder' => 'AAPL, Tech, Breakout'])->label('Tags (Pisahkan koma)') ?>
                </div>
            </div>

            <!-- Featured Image Preview -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-image text-info me-2"></i> Gambar Screenshot Chart</h5>
                </div>
                <div class="card-body p-4 text-center">
                    <?php if (!empty($model->featured_image)): ?>
                        <div class="rounded-3 overflow-hidden mb-3 border bg-light" style="aspect-ratio: 16/9;">
                            <img src="<?= Html::encode(Yii::getAlias('@web') . $model->featured_image) ?>" alt="Featured" class="w-100 h-100 object-fit-cover">
                        </div>
                    <?php else: ?>
                        <div class="rounded-3 p-4 mb-3 border border-dashed bg-light text-muted small">
                            <i class="bi bi-image fs-1 d-block mb-1 text-secondary"></i>
                            Belum ada gambar screenshot. Gambar akan terunggah otomatis saat menggunakan tombol generator di detail saham.
                        </div>
                    <?php endif; ?>

                    <?= $form->field($model, 'featured_image')->textInput(['placeholder' => '/uploads/blog/....webp'])->label('Path Berkas Gambar') ?>
                </div>
            </div>

            <!-- Tombol Submit -->
            <div class="d-grid gap-2">
                <?= Html::submitButton($isNew ? '🚀 Publikasikan Artikel' : '💾 Simpan Perubahan', ['class' => 'btn btn-primary btn-lg rounded-pill fw-bold shadow-sm']) ?>
                <a href="<?= Url::to(['/blog/admin']) ?>" class="btn btn-outline-secondary rounded-pill">Batal</a>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>
