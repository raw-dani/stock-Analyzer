<?php

/** @var yii\web\View $this */
/** @var app\models\form\ScanFilterForm $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $stats */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;
use yii\grid\GridView;

$this->title = 'Volume & Accumulation Scanner';
$this->params['breadcrumbs'][] = $this->title;

$isDaily = $searchModel->mode === 'daily';
$stats = $stats ?? [
    'total' => $dataProvider->getTotalCount(),
    'strongBuy' => 0,
    'heavyAccumulation' => 0,
    'avgRvol' => 0.0,
];

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
        'headerOptions' => ['style' => 'width: 90px'],
        'value' => function ($m) {
            return Html::a(
                Html::encode($m->stock->symbol),
                ['/stock/view', 'symbol' => $m->stock->symbol],
                ['class' => 'fw-bold text-primary text-decoration-none fs-6']
            );
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'sector',
        'label' => 'Sektor / Industri',
        'value' => function ($m) {
            return '<div class="small fw-semibold text-dark">' . Html::encode($m->stock->name ?? $m->stock->symbol) . '</div>'
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
            $color = $pct >= 70 ? 'text-success' : ($pct >= 50 ? 'text-warning' : 'text-danger');
            
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
            $val = round($m->rvol, 1);
            if ($val >= 2.0) {
                return '<span class="badge bg-danger text-white px-2 py-1"><i class="bi bi-fire"></i> ' . $val . 'x</span>';
            } elseif ($val >= 1.5) {
                return '<span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-lightning-fill"></i> ' . $val . 'x</span>';
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
        'headerOptions' => ['class' => 'text-end', 'style' => 'width: 90px'],
        'contentOptions' => ['class' => 'text-end small'],
        'value' => function ($m) {
            if ($m->volume_growth === null) return '-';
            $color = $m->volume_growth >= 0.5 ? 'text-success fw-bold' : ($m->volume_growth >= 0 ? 'text-warning' : 'text-danger');
            return Html::tag('span', Yii::$app->formatter->asPercent($m->volume_growth), ['class' => $color]);
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
            $color = $sc >= 70 ? 'success' : ($sc >= 50 ? 'warning' : 'secondary');
            return '<span class="badge bg-' . $color . '">' . $sc . '</span>';
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
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 140px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            $sym = $m->stock->symbol;
            return '<div class="btn-group btn-group-sm">'
                . Html::a('DB', ['/double-bottom/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-primary py-0 px-2', 'title' => 'Cek Pola Double Bottom'])
                . Html::a('RSI', ['/rsi-double-bottom/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-info py-0 px-2', 'title' => 'Cek Pola RSI Double Bottom'])
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
        'headerOptions' => ['style' => 'width: 90px'],
        'value' => function ($m) {
            return Html::a(
                Html::encode($m->stock->symbol),
                ['/stock/view', 'symbol' => $m->stock->symbol],
                ['class' => 'fw-bold text-primary text-decoration-none fs-6']
            );
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'sector',
        'label' => 'Sektor / Industri',
        'value' => function ($m) {
            return '<div class="small fw-semibold text-dark">' . Html::encode($m->stock->name ?? $m->stock->symbol) . '</div>'
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
        'attribute' => 'volume',
        'label' => 'Volume Total',
        'headerOptions' => ['class' => 'text-end'],
        'contentOptions' => ['class' => 'text-end small'],
        'value' => function ($m) {
            return $m->volume !== null ? Yii::$app->formatter->asInteger($m->volume) : '-';
        },
    ],
    [
        'label' => 'Buy Ratio Harian',
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 140px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            $total = (int) $m->buy_volume + (int) $m->sell_volume;
            if ($total <= 0 || $m->buy_volume === null) return '-';
            $ratio = $m->buy_volume / $total;
            $pct = round($ratio * 100, 1);
            $sellPct = round(100 - $pct, 1);
            $color = $ratio >= 0.7 ? 'text-success' : ($ratio >= 0.5 ? 'text-warning' : 'text-danger');

            return '<div class="progress" style="height: 6px; width: 100%;" title="Buy: ' . $pct . '% | Sell: ' . $sellPct . '%">'
                . '<div class="progress-bar bg-success" style="width: ' . $pct . '%"></div>'
                . '<div class="progress-bar bg-danger" style="width: ' . $sellPct . '%"></div>'
                . '</div>'
                . '<div class="small fw-bold ' . $color . ' mt-1" style="font-size: 0.78rem;">' . $pct . '% Buy</div>';
        },
        'format' => 'raw',
    ],
    [
        'label' => 'Aksi Pola',
        'headerOptions' => ['class' => 'text-center', 'style' => 'width: 140px'],
        'contentOptions' => ['class' => 'text-center'],
        'value' => function ($m) {
            $sym = $m->stock->symbol;
            return '<div class="btn-group btn-group-sm">'
                . Html::a('DB', ['/double-bottom/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-primary py-0 px-2', 'title' => 'Cek Pola Double Bottom'])
                . Html::a('RSI', ['/rsi-double-bottom/detail', 'symbol' => $sym], ['class' => 'btn btn-outline-info py-0 px-2', 'title' => 'Cek Pola RSI Double Bottom'])
                . '</div>';
        },
        'format' => 'raw',
    ],
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">
            <i class="bi bi-funnel-fill text-primary"></i> <?= Html::encode($this->title) ?>
        </h1>
        <p class="text-muted small mb-0">Deteksi aliran dana institusi / bandar melalui analisis rasio volume beli vs jual, lonjakan RVOL, dan scoring komposit.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= \yii\helpers\Url::to(['scanner/run-scan']) ?>" class="btn btn-warning btn-sm fw-bold px-3 shadow-sm">
            <i class="bi bi-arrow-clockwise"></i> Jalankan Scan Harian
        </a>
    </div>
</div>

<?php foreach ((array) Yii::$app->session->getFlash('success') as $msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i> <?= Html::encode($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>
<?php foreach ((array) Yii::$app->session->getFlash('danger') as $msg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= Html::encode($msg) ?>
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

<!-- KPI Metric Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-light text-center h-100 py-2">
            <div class="card-body p-2">
                <div class="text-muted small fw-semibold text-uppercase">Total Saham Lolos</div>
                <div class="h2 mb-0 fw-bold text-dark"><?= number_format((int) ($stats['total'] ?? 0)) ?></div>
                <div class="small text-muted">Sesuai Kriteria Filter</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 text-center h-100 py-2 border-start border-success border-4">
            <div class="card-body p-2">
                <div class="text-success small fw-semibold text-uppercase">Strong Buy Signals</div>
                <div class="h2 mb-0 fw-bold text-success"><?= number_format((int) ($stats['strongBuy'] ?? 0)) ?></div>
                <div class="small text-success">Dominasi Akumulasi Kuat</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10 text-center h-100 py-2 border-start border-primary border-4">
            <div class="card-body p-2">
                <div class="text-primary small fw-semibold text-uppercase">Akumulasi &ge; 70%</div>
                <div class="h2 mb-0 fw-bold text-primary"><?= number_format((int) ($stats['heavyAccumulation'] ?? 0)) ?></div>
                <div class="small text-primary">Tekanan Beli Tinggi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10 text-center h-100 py-2 border-start border-warning border-4">
            <div class="card-body p-2">
                <div class="text-warning-emphasis small fw-semibold text-uppercase">Rata-rata RVOL</div>
                <div class="h2 mb-0 fw-bold text-warning-emphasis"><?= number_format((float) ($stats['avgRvol'] ?? 0), 1) ?>x</div>
                <div class="small text-muted">Aktivitas Volume Relatif</div>
            </div>
        </div>
    </div>
</div>

<!-- Search Filter Form with Presets -->
<?= $this->render('_search', ['model' => $searchModel]) ?>

<!-- Table Results -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="bi bi-table text-primary me-1"></i> Daftar Saham Terfilter
            <span class="badge bg-secondary ms-1"><?= $dataProvider->getTotalCount() ?> Saham</span>
        </h5>
        <div class="small text-muted">
            Tampilan: <strong><?= strtoupper($searchModel->mode ?? 'weekly') ?></strong>
        </div>
    </div>
    <div class="table-responsive">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => null,
            'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
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
