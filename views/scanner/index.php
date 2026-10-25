<?php

/** @var yii\web\View $this */
/** @var app\models\form\ScanFilterForm $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $stats */
/** @var array $symbols */
/** @var array $tvSymbols */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;
use yii\grid\GridView;
use yii\helpers\Url;

$this->title = 'Stock Scanner - Volume & Accumulation';
$this->params['breadcrumbs'][] = 'Stock Scanner';

$isDaily = ($searchModel->mode ?? 'weekly') === 'daily';
$stats = $stats ?? [
    'total' => $dataProvider->getTotalCount(),
    'strongBuy' => 0,
    'heavyAccumulation' => 0,
    'avgRvol' => 0.0,
];

$symbols = $symbols ?? [];
$tvSymbols = $tvSymbols ?? [];
$symbolsStr = implode(', ', $symbols);
$tvSymbolsStr = implode(', ', $tvSymbols);

// Filter params generator
$currentGet = Yii::$app->request->get();
$filterWith = fn(array $overrides) => Url::to(array_merge(['/scanner/index'], $currentGet, $overrides));

$weeklyColumns = [
    ['class' => \yii\grid\SerialColumn::class, 'header' => '#', 'headerOptions' => ['style' => 'width: 40px']],
    [
        'attribute' => 'week_start',
        'label' => 'Tanggal',
        'headerOptions' => ['style' => 'width: 95px'],
        'value' => function ($m) {
            return Yii::$app->formatter->asDate($m->week_start, 'd MMM y');
        },
    ],
    [
        'attribute' => 'symbol',
        'label' => 'Simbol',
        'headerOptions' => ['style' => 'width: 105px'],
        'value' => function ($m) {
            $sym = Html::encode($m->stock->symbol);
            $exchange = Html::encode($m->stock->exchange ?? '');
            $exchBadge = $exchange ? ' <span class="badge bg-light text-secondary border py-0 px-1" style="font-size:0.65rem;">' . $exchange . '</span>' : '';
            return '<div class="d-flex align-items-center gap-1">'
                . Html::a($sym, ['/stock/view', 'symbol' => $m->stock->symbol], ['class' => 'fw-bold text-primary text-decoration-none fs-6'])
                . $exchBadge
                . '</div>';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'sector',
        'label' => 'Sektor / Industri',
        'value' => function ($m) {
            return '<div class="small fw-semibold text-dark text-truncate" style="max-width: 220px;" title="' . Html::encode($m->stock->name ?? '') . '">' . Html::encode($m->stock->name ?? $m->stock->symbol) . '</div>'
                . '<div class="small text-muted" style="font-size: 0.75rem;">' . Html::encode($m->stock->sector ?? 'Unassigned') . '</div>';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'price',
        'label' => 'Harga',
        'headerOptions' => ['class' => 'text-end'],
        'contentOptions' => ['class' => 'text-end fw-bold text-dark'],
        'value' => function ($m) {
            return Yii::$app->formatter->asCurrency($m->close_price);
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'market_cap',
        'label' => 'Mkt Cap',
        'headerOptions' => ['class' => 'text-end'],
        'contentOptions' => ['class' => 'text-end small text-muted'],
        'value' => function ($m) {
            return $m->stock->market_cap !== null ? Yii::$app->formatter->asVolume($m->stock->market_cap) : '-';
        },
    ],
    [
        'attribute' => 'buy_ratio',
        'label' => 'Akumulasi (Buy Ratio)',
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 140px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            if ($m->buy_ratio === null) return '-';
            $pct = round($m->buy_ratio * 100, 1);
            $sellPct = round(100 - $pct, 1);
            $color = $pct >= 70 ? 'text-success' : ($pct >= 50 ? 'text-warning-emphasis' : 'text-danger');
            
            return '<div class="progress" style="height: 6px; width: 100%;" title="Buy: ' . $pct . '% | Sell: ' . $sellPct . '%">'
                . '<div class="progress-bar bg-success" style="width: ' . $pct . '%"></div>'
                . '<div class="progress-bar bg-danger" style="width: ' . $sellPct . '%"></div>'
                . '</div>'
                . '<div class="small fw-bold ' . $color . ' mt-1" style="font-size: 0.78rem;">' . $pct . '% Buy</div>';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'rvol',
        'label' => 'RVOL',
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 95px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            if ($m->rvol === null) return '-';
            $val = round((float) $m->rvol, 1);
            if ($val >= 2.0) {
                return '<span class="badge bg-danger text-white px-2 py-1 shadow-sm" title="Lonjakan Volume Kuat ≥2.0x">' . \app\helpers\SvgHelper::icon('fire') . ' ' . $val . 'x</span>';
            } elseif ($val >= 1.5) {
                return '<span class="badge bg-warning text-dark px-2 py-1 shadow-sm" title="Volume Meningkat ≥1.5x">' . \app\helpers\SvgHelper::icon('lightning-fill') . ' ' . $val . 'x</span>';
            } elseif ($val >= 1.0) {
                return '<span class="badge bg-primary px-2 py-1">' . $val . 'x</span>';
            }
            return '<span class="badge bg-light text-muted border">' . $val . 'x</span>';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'volume_growth',
        'label' => 'Vol Growth',
        'headerOptions' => ['class' => 'text-end', 'style' => 'width: 95px'],
        'contentOptions' => ['class' => 'text-end small'],
        'value' => function ($m) {
            if ($m->volume_growth === null) return '-';
            $color = $m->volume_growth >= 0.5 ? 'text-success fw-bold' : ($m->volume_growth >= 0 ? 'text-warning-emphasis' : 'text-danger');
            $prefix = $m->volume_growth > 0 ? '+' : '';
            return Html::tag('span', $prefix . Yii::$app->formatter->asPercent($m->volume_growth), ['class' => $color]);
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'score',
        'label' => 'Skor',
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 70px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            if ($m->score === null) return '-';
            $sc = (int) $m->score;
            $color = $sc >= 70 ? 'success' : ($sc >= 50 ? 'warning text-dark' : 'secondary');
            return '<span class="badge bg-' . $color . ' px-2 py-1" title="Skor Komposit: ' . $sc . '/100">' . $sc . '</span>';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'signal',
        'label' => 'Sinyal',
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 110px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            return SignalBadge::widget(['signal' => $m->signal]);
        },
        'format' => 'raw',
    ],
    [
        'label' => 'Aksi Pola',
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 175px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            $sym = $m->stock->symbol;
            return '<div class="btn-group btn-group-sm" role="group">'
                . Html::a('CF', ['/strategy-confluence/index', 'symbol' => $sym], ['class' => 'btn btn-primary py-0 px-2 fw-bold', 'title' => 'Cek Confluence Multi-Strategi'])
                . Html::a('DB', ['/double-bottom/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-primary py-0 px-2', 'title' => 'Cek Pola Double Bottom'])
                . Html::a('TB', ['/triple-bottom/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-success py-0 px-2', 'title' => 'Cek Pola Triple Bottom'])
                . Html::a('RSI', ['/rsi-double-bottom/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-info py-0 px-2', 'title' => 'Cek Pola RSI Double Bottom'])
                . Html::a('MA', ['/moving-average/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-warning text-dark py-0 px-2', 'title' => 'Cek Moving Average'])
                . Html::a(\app\helpers\SvgHelper::icon('box-arrow-up-right'), ['/stock/view', 'symbol' => $sym], ['class' => 'btn btn-outline-secondary py-0 px-1', 'title' => 'Detail Saham'])
                . '</div>';
        },
        'format' => 'raw',
    ],
];

$dailyColumns = [
    ['class' => \yii\grid\SerialColumn::class, 'header' => '#', 'headerOptions' => ['style' => 'width: 40px']],
    [
        'attribute' => 'date',
        'label' => 'Tanggal',
        'headerOptions' => ['style' => 'width: 95px'],
        'value' => function ($m) {
            return Yii::$app->formatter->asDate($m->date, 'd MMM y');
        },
    ],
    [
        'attribute' => 'symbol',
        'label' => 'Simbol',
        'headerOptions' => ['style' => 'width: 105px'],
        'value' => function ($m) {
            $sym = Html::encode($m->stock->symbol);
            $exchange = Html::encode($m->stock->exchange ?? '');
            $exchBadge = $exchange ? ' <span class="badge bg-light text-secondary border py-0 px-1" style="font-size:0.65rem;">' . $exchange . '</span>' : '';
            return '<div class="d-flex align-items-center gap-1">'
                . Html::a($sym, ['/stock/view', 'symbol' => $m->stock->symbol], ['class' => 'fw-bold text-primary text-decoration-none fs-6'])
                . $exchBadge
                . '</div>';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'sector',
        'label' => 'Sektor / Industri',
        'value' => function ($m) {
            return '<div class="small fw-semibold text-dark text-truncate" style="max-width: 220px;" title="' . Html::encode($m->stock->name ?? '') . '">' . Html::encode($m->stock->name ?? $m->stock->symbol) . '</div>'
                . '<div class="small text-muted" style="font-size: 0.75rem;">' . Html::encode($m->stock->sector ?? 'Unassigned') . '</div>';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'close',
        'label' => 'Harga Close',
        'headerOptions' => ['class' => 'text-end'],
        'contentOptions' => ['class' => 'text-end fw-bold text-dark'],
        'value' => function ($m) {
            return Yii::$app->formatter->asCurrency($m->close);
        },
        'format' => 'raw',
    ],
    [
        'label' => 'Perubahan Hari Ini',
        'headerOptions' => ['class' => 'text-end', 'style' => 'width: 120px'],
        'contentOptions' => ['class' => 'text-end'],
        'value' => function ($m) {
            if (empty($m->open) || $m->open <= 0) return '-';
            $diff = (float) $m->close - (float) $m->open;
            $pct = round(($diff / (float) $m->open) * 100, 2);
            $badgeClass = $pct > 0 ? 'text-success fw-bold' : ($pct < 0 ? 'text-danger fw-bold' : 'text-muted');
            $icon = $pct > 0 ? \app\helpers\SvgHelper::icon('caret-up-fill') . ' +' : ($pct < 0 ? \app\helpers\SvgHelper::icon('caret-down-fill') . ' ' : '');
            return '<span class="' . $badgeClass . '">' . $icon . $pct . '%</span>';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'volume',
        'label' => 'Volume Total',
        'headerOptions' => ['class' => 'text-end'],
        'contentOptions' => ['class' => 'text-end small'],
        'value' => function ($m) {
            return $m->volume !== null ? Yii::$app->formatter->asInteger($m->volume) : '-';
        },
    ],
    [
        'label' => 'Buy Pressure (Harian)',
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 140px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            $total = (int) $m->buy_volume + (int) $m->sell_volume;
            if ($total <= 0 || $m->buy_volume === null) return '-';
            $ratio = $m->buy_volume / $total;
            $pct = round($ratio * 100, 1);
            $sellPct = round(100 - $pct, 1);
            $color = $ratio >= 0.7 ? 'text-success' : ($ratio >= 0.5 ? 'text-warning-emphasis' : 'text-danger');

            return '<div class="progress" style="height: 6px; width: 100%;" title="Buy: ' . $pct . '% | Sell: ' . $sellPct . '%">'
                . '<div class="progress-bar bg-success" style="width: ' . $pct . '%"></div>'
                . '<div class="progress-bar bg-danger" style="width: ' . $sellPct . '%"></div>'
                . '</div>'
                . '<div class="small fw-bold ' . $color . ' mt-1" style="font-size: 0.78rem;">' . $pct . '% Buy</div>';
        },
        'format' => 'raw',
    ],
    [
        'label' => 'Kategori Day Trade',
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 125px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            $total = (int) $m->buy_volume + (int) $m->sell_volume;
            $ratio = $total > 0 ? (int) $m->buy_volume / $total : 0.5;
            $gainPct = !empty($m->open) && $m->open > 0 ? (($m->close - $m->open) / $m->open) * 100 : 0;

            if ($gainPct >= 2.0 && $ratio >= 0.65) {
                return '<span class="badge bg-success text-white shadow-sm" title="Momentum breakout kuat dengan akumulasi dominan">' . \app\helpers\SvgHelper::icon('rocket-takeoff-fill') . ' BREAKOUT</span>';
            } elseif ($ratio >= 0.70) {
                return '<span class="badge bg-primary text-white shadow-sm" title="Tekanan beli tinggi">' . \app\helpers\SvgHelper::icon('buildings') . ' AKUMULASI</span>';
            } elseif ($gainPct >= 3.0) {
                return '<span class="badge bg-warning text-dark shadow-sm" title="Kenaikan harga tajam">' . \app\helpers\SvgHelper::icon('lightning-fill') . ' MOMENTUM</span>';
            } elseif ($gainPct <= -1.0 && $ratio >= 0.60) {
                return '<span class="badge bg-info text-white shadow-sm" title="Harga turun namun volume beli terserap (Buy on weakness)">' . \app\helpers\SvgHelper::icon('arrow-down-left-square') . ' ABSORPTION</span>';
            }
            return '<span class="badge bg-light text-muted border">NETRAL</span>';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'market_cap',
        'label' => 'Mkt Cap',
        'headerOptions' => ['class' => 'text-end'],
        'contentOptions' => ['class' => 'text-end small text-muted'],
        'value' => function ($m) {
            return $m->stock->market_cap !== null ? Yii::$app->formatter->asVolume($m->stock->market_cap) : '-';
        },
    ],
    [
        'label' => 'Aksi Pola',
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 155px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            $sym = $m->stock->symbol;
            return '<div class="btn-group btn-group-sm" role="group">'
                . Html::a('DB', ['/double-bottom/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-primary py-0 px-2', 'title' => 'Cek Pola Double Bottom'])
                . Html::a('TB', ['/triple-bottom/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-success py-0 px-2', 'title' => 'Cek Pola Triple Bottom'])
                . Html::a('RSI', ['/rsi-double-bottom/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-info py-0 px-2', 'title' => 'Cek Pola RSI Double Bottom'])
                . Html::a('MA', ['/moving-average/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-warning text-dark py-0 px-2', 'title' => 'Cek Posisi Beli/Jual Moving Average'])
                . Html::a(\app\helpers\SvgHelper::icon('box-arrow-up-right'), ['/stock/view', 'symbol' => $sym], ['class' => 'btn btn-outline-secondary py-0 px-1', 'title' => 'Detail Saham'])
                . '</div>';
        },
        'format' => 'raw',
    ],
];
?>

<div class="app-hero-header mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="app-hero-icon text-primary bg-primary bg-opacity-10">
                <?= \app\helpers\SvgHelper::icon('funnel-fill') ?>
            </div>
            <div>
                <h1 class="app-hero-title mb-1">
                    <?= Html::encode($this->title) ?>
                </h1>
                <p class="app-hero-desc mb-0">
                    Deteksi aliran dana institusi / bandar melalui analisis rasio volume beli vs jual, lonjakan RVOL, dan scoring komposit.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="btn-group-tf shadow-sm">
                <?= Html::a(\app\helpers\SvgHelper::icon('calendar3-week', ['class' => 'me-1']) . ' Mode Weekly', $filterWith(['mode' => 'weekly']), [
                    'class' => 'btn-tf ' . (!$isDaily ? 'active' : ''),
                ]) ?>
                <?= Html::a(\app\helpers\SvgHelper::icon('calendar-day', ['class' => 'me-1']) . ' Mode Daily', $filterWith(['mode' => 'daily']), [
                    'class' => 'btn-tf ' . ($isDaily ? 'active' : ''),
                ]) ?>
            </div>
            <a href="<?= Url::to(array_merge(['scanner/run-scan'], $currentGet)) ?>" class="btn btn-warning btn-sm fw-bold px-3 shadow-sm rounded-pill">
                <?= \app\helpers\SvgHelper::icon('arrow-clockwise') ?> <?= $isDaily ? 'Scan Harian' : 'Scan Mingguan' ?>
            </a>
        </div>
    </div>
</div>

<?= $this->render('//layouts/_sync_status_badge', [
    'fromCache' => true,
    'timeframe' => 24,
]) ?>

<?php foreach ((array) Yii::$app->session->getFlash('success') as $msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'me-1']) ?> <?= Html::encode($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>
<?php foreach ((array) Yii::$app->session->getFlash('danger') as $msg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= \app\helpers\SvgHelper::icon('exclamation-triangle-fill', ['class' => 'me-1']) ?> <?= Html::encode($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>

<?php if ($searchModel->hasErrors()): ?>
    <div class="alert alert-warning" role="alert">
        <strong>Filter tidak valid — hasil dikosongkan agar tidak menyesatkan:</strong>
        <ul class="mb-0 ps-3">
            <?php foreach ($searchModel->getErrors() as $attribute => $messages): ?>
                <?php foreach ($messages as $message): ?>
                    <li><?= Html::encode($message) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- KPI Metric Summary Cards (Interactive Quick Filters) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= Url::to(['/scanner/index', 'mode' => $searchModel->mode]) ?>" class="text-decoration-none">
            <div class="kpi-card-unified kpi-accent-indigo h-100">
                <div class="kpi-card-label">Total Saham Lolos</div>
                <div class="kpi-card-value text-indigo-700"><?= number_format((int) ($stats['total'] ?? 0)) ?></div>
                <div class="kpi-card-sub text-muted">
                    <?= \app\helpers\SvgHelper::icon('arrow-counterclockwise', ['class' => 'me-1']) ?> Reset filter
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $isDaily ? $filterWith(['minBuyRatio' => '0.7', 'minChangePct' => '2.0']) : $filterWith(['signal' => 'STRONG_BUY']) ?>" class="text-decoration-none">
            <div class="kpi-card-unified kpi-accent-emerald h-100 <?= ((!$isDaily && $searchModel->signal === 'STRONG_BUY') || ($isDaily && $searchModel->minBuyRatio == 0.7 && $searchModel->minChangePct == 2.0)) ? 'kpi-selected' : '' ?>">
                <div class="kpi-card-label"><?= $isDaily ? 'Strong Breakout' : 'Strong Buy Signals' ?></div>
                <div class="kpi-card-value text-emerald-700"><?= number_format((int) ($stats['strongBuy'] ?? 0)) ?></div>
                <div class="kpi-card-sub text-emerald-600">
                    <?= \app\helpers\SvgHelper::icon('gem', ['class' => 'me-1']) ?> <?= $isDaily ? 'Tekanan Beli & Gain Tinggi' : 'Dominasi Akumulasi Kuat' ?>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['minBuyRatio' => '0.7']) ?>" class="text-decoration-none">
            <div class="kpi-card-unified kpi-accent-cyan h-100 <?= ($searchModel->minBuyRatio == 0.7) ? 'kpi-selected' : '' ?>">
                <div class="kpi-card-label">Akumulasi &ge; 70%</div>
                <div class="kpi-card-value text-cyan-700"><?= number_format((int) ($stats['heavyAccumulation'] ?? 0)) ?></div>
                <div class="kpi-card-sub text-cyan-600">
                    <?= \app\helpers\SvgHelper::icon('buildings', ['class' => 'me-1']) ?> Tekanan Beli Tinggi
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $isDaily ? $filterWith(['minChangePct' => '2.0']) : $filterWith(['minRvol' => '1.5']) ?>" class="text-decoration-none">
            <div class="kpi-card-unified kpi-accent-amber h-100 <?= (($isDaily && $searchModel->minChangePct == 2.0) || (!$isDaily && $searchModel->minRvol == 1.5)) ? 'kpi-selected' : '' ?>">
                <div class="kpi-card-label"><?= $isDaily ? 'Rata-rata Buy Pressure' : 'Rata-rata RVOL' ?></div>
                <div class="kpi-card-value text-amber-700"><?= number_format((float) ($stats['avgRvol'] ?? 0), 1) ?><?= $isDaily ? '%' : 'x' ?></div>
                <div class="kpi-card-sub text-amber-600">
                    <?= \app\helpers\SvgHelper::icon('lightning-fill', ['class' => 'me-1']) ?> <?= $isDaily ? 'Intensitas Akumulasi' : 'Aktivitas Relatif Mingguan' ?>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Search Filter Form with Presets -->
<?= $this->render('_search', ['model' => $searchModel]) ?>

<!-- Table Results with Toolbar -->
<div class="table-card-unified">
    <div class="table-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 fw-bold text-dark">
                <?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary me-1']) ?> Daftar Saham Terfilter
                <span class="badge bg-secondary ms-1"><?= $dataProvider->getTotalCount() ?> Saham</span>
            </h5>
            <span class="badge bg-light text-dark border">
                Tampilan: <strong><?= strtoupper($searchModel->mode ?? 'weekly') ?></strong>
            </span>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Client-side quick filter input -->
            <div class="input-group input-group-sm" style="max-width: 220px;">
                <span class="input-group-text bg-light border-end-0"><?= \app\helpers\SvgHelper::icon('search', ['class' => 'text-muted']) ?></span>
                <input type="text" id="scanner-table-search" class="form-control form-control-sm border-start-0" placeholder="Filter di tabel...">
            </div>

            <!-- Copy Symbols Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" <?= empty($symbols) ? 'disabled' : '' ?>>
                    <?= \app\helpers\SvgHelper::icon('clipboard') ?> Salin Simbol (<?= count($symbols) ?>)
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                    <li><h6 class="dropdown-header">Pilih Format Simbol</h6></li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0)" onclick="copySymbolsText('<?= Html::encode($symbolsStr) ?>', 'Simbol standar')">
                            <?= \app\helpers\SvgHelper::icon('file-text', ['class' => 'me-1']) ?> Standar (Cth: NVDA, AAPL)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0)" onclick="copySymbolsText('<?= Html::encode($tvSymbolsStr) ?>', 'Format TradingView')">
                            <?= \app\helpers\SvgHelper::icon('graph-up', ['class' => 'me-1']) ?> TradingView (Cth: NASDAQ:NVDA)
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Export CSV -->
            <a href="<?= Url::to(array_merge(['scanner/export'], $currentGet)) ?>" class="btn btn-outline-success btn-sm shadow-sm" title="Download data hasil filter dalam format CSV">
                <?= \app\helpers\SvgHelper::icon('download') ?> Export CSV
            </a>
        </div>
    </div>

    <!-- Toast Notification for Copy -->
    <div id="copy-toast" class="alert alert-dark position-fixed bottom-0 end-0 m-3 shadow py-2 px-3 fade" style="display:none; z-index:9999;" role="alert">
        <?= \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success me-1']) ?> <span id="copy-toast-text">Simbol disalin ke clipboard!</span>
    </div>

    <div class="table-responsive">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => null,
            'emptyText' => '<div class="text-center py-5">
                <div class="display-6 text-muted mb-2">' . \app\helpers\SvgHelper::icon('funnel') . '</div>
                <h5 class="fw-bold text-secondary">Tidak ada saham yang cocok dengan filter</h5>
                <p class="text-muted small mb-3">Coba longgarkan kriteria filter Anda (misal kurangi Min Skor, Min Buy Ratio, atau pilih Semua Sinyal).</p>
                <a href="' . Url::to(['/scanner/index', 'mode' => $searchModel->mode]) . '" class="btn btn-primary btn-sm px-3 shadow-sm">
                    ' . \app\helpers\SvgHelper::icon('arrow-counterclockwise') . ' Reset Semua Filter
                </a>
            </div>',
            'tableOptions' => ['class' => 'table-unified', 'id' => 'scanner-grid-table'],
            'headerRowOptions' => ['class' => 'table-light small text-uppercase text-muted'],
            'rowOptions' => function ($m) use ($isDaily) {
                if ($isDaily) {
                    $total = (int) $m->buy_volume + (int) $m->sell_volume;
                    $ratio = $total > 0 ? (int) $m->buy_volume / $total : 0.5;
                    return ['class' => $ratio >= 0.7 ? 'table-success bg-opacity-10' : ''];
                }
                return ['class' => ($m->signal === 'STRONG_BUY' ? 'table-success bg-opacity-10' : '')];
            },
            'columns' => $isDaily ? $dailyColumns : $weeklyColumns,
        ]); ?>
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.1) !important;
}
.ring-active {
    box-shadow: 0 0 0 2px #0d6efd !important;
}
</style>

<script>
// Live client-side instant filtering on the table
document.getElementById('scanner-table-search')?.addEventListener('input', function () {
    const filter = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#scanner-grid-table tbody tr');
    rows.forEach(row => {
        if (row.classList.contains('empty')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

// Copy symbols to clipboard with visual feedback
function copySymbolsText(text, label) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(function() {
        const toast = document.getElementById('copy-toast');
        const toastText = document.getElementById('copy-toast-text');
        if (toast && toastText) {
            toastText.textContent = label + ' berhasil disalin ke clipboard!';
            toast.style.display = 'block';
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => { toast.style.display = 'none'; }, 200);
            }, 2500);
        }
    }).catch(function(err) {
        alert('Gagal menyalin: ' + err);
    });
}
</script>
