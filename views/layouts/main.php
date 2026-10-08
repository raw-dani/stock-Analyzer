<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;

AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);

// menu sidebar (task 5.1 & complete feature navigation)
$sideMenuGroups = [
    'Overview' => [
        ['label' => '📊 Dashboard', 'url' => ['/dashboard/index'], 'active' => Yii::$app->controller->id === 'dashboard'],
        ['label' => '⭐ Watchlist', 'url' => ['/watchlist/index'], 'active' => Yii::$app->controller->id === 'watchlist'],
    ],
    'Scanners & Patterns' => [
        ['label' => '📉 Double Bottom (Core)', 'url' => ['/double-bottom/index'], 'active' => Yii::$app->controller->id === 'double-bottom', 'badge' => 'Core'],
        ['label' => '📉 Triple Bottom Scanner', 'url' => ['/triple-bottom/index'], 'active' => Yii::$app->controller->id === 'triple-bottom', 'badge' => 'New'],
        ['label' => '📉 RSI Double Bottom', 'url' => ['/rsi-double-bottom/index'], 'active' => str_starts_with(Yii::$app->controller->id, 'rsi-double-bottom')],
        ['label' => '🔍 Volume Scanner', 'url' => ['/scanner/index'], 'active' => Yii::$app->controller->id === 'scanner'],
    ],
    'Market Intelligence' => [
        ['label' => '🗺️ Sectors', 'url' => ['/sector/index'], 'active' => Yii::$app->controller->id === 'sector'],
        ['label' => '📈 Signals', 'url' => ['/signal/index'], 'active' => Yii::$app->controller->id === 'signal'],
    ],
    'Strategy & Tools' => [
        ['label' => '🧪 Backtesting', 'url' => ['/backtest/index'], 'active' => Yii::$app->controller->id === 'backtest'],
        ['label' => '🔔 Alerts', 'url' => ['/alert/index'], 'active' => Yii::$app->controller->id === 'alert'],
    ],
];
$sideMenuGroups['System'] = [
    ['label' => '⚙️ Settings', 'url' => ['/settings/index'], 'active' => Yii::$app->controller->id === 'settings'],
];
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
    <style>
        .sidebar { min-height: calc(100vh - 56px); border-right: 1px solid #dee2e6; background-color: #f8f9fa; }
        .sidebar .nav-link { color: #495057; border-radius: .375rem; font-weight: 500; padding: .5rem .75rem; }
        .sidebar .nav-link:hover { background: #e9ecef; color: #0d6efd; }
        .sidebar .nav-link.active { background: #0d6efd; color: #fff; }
        .sidebar-heading { font-size: .72rem; font-weight: 700; text-transform: uppercase; color: #6c757d; letter-spacing: .05rem; padding: .6rem .75rem .25rem; }
        .kpi { font-size: 1.6rem; font-weight: 700; }
        .badge-core { background: linear-gradient(135deg, #0d6efd, #0dcaf0); color: #fff; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<header id="header">
    <?php
    NavBar::begin([
        'brandLabel' => '📈 Stock Volume Analyzer',
        'brandUrl' => Yii::$app->homeUrl,
        'options' => ['class' => 'navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm']
    ]);
    
    // Top navigation with direct access to all main features
    $topNavItems = [
        ['label' => '📊 Dashboard', 'url' => ['/dashboard/index'], 'active' => Yii::$app->controller->id === 'dashboard'],
        [
            'label' => '📉 Scanners & Patterns',
            'active' => in_array(Yii::$app->controller->id, ['double-bottom', 'triple-bottom', 'rsi-double-bottom', 'scanner'], true),
            'items' => [
                ['label' => '📉 Double Bottom Scanner (Core)', 'url' => ['/double-bottom/index']],
                ['label' => '📉 Triple Bottom Scanner', 'url' => ['/triple-bottom/index']],
                ['label' => '📉 RSI Double Bottom', 'url' => ['/rsi-double-bottom/index']],
                '<div class="dropdown-divider"></div>',
                ['label' => '🔍 Volume Scanner', 'url' => ['/scanner/index']],
            ],
        ],
        [
            'label' => '🗺️ Market',
            'active' => in_array(Yii::$app->controller->id, ['sector', 'watchlist', 'signal'], true),
            'items' => [
                ['label' => '🗺️ Sectors', 'url' => ['/sector/index']],
                ['label' => '⭐ Watchlist', 'url' => ['/watchlist/index']],
                ['label' => '📈 Signals', 'url' => ['/signal/index']],
            ],
        ],
        [
            'label' => '🧪 Tools',
            'active' => in_array(Yii::$app->controller->id, ['backtest', 'alert', 'settings'], true),
            'items' => [
                ['label' => '🧪 Backtesting', 'url' => ['/backtest/index']],
                ['label' => '🔔 Alerts', 'url' => ['/alert/index']],
                '<div class="dropdown-divider"></div>',
                ['label' => '⚙️ Settings', 'url' => ['/settings/index']],
            ],
        ],
    ];

    echo Nav::widget([
        'options' => ['class' => 'navbar-nav ms-auto'],
        'items' => $topNavItems,
    ]);
    NavBar::end();
    ?>
</header>

<main id="main" class="flex-shrink-0" role="main">
    <div class="container-fluid">
        <div class="row">
            <aside class="col-md-3 col-lg-2 d-none d-md-block pt-3 sidebar">
                <nav class="nav flex-column gap-1">
                    <?php foreach ($sideMenuGroups as $groupTitle => $items): ?>
                        <div class="sidebar-heading"><?= Html::encode($groupTitle) ?></div>
                        <?php foreach ($items as $item): ?>
                            <?= Html::a(
                                $item['label'] . (!empty($item['badge']) ? ' <span class="badge badge-core float-end mt-1">' . $item['badge'] . '</span>' : ''),
                                $item['url'],
                                [
                                    'class' => 'nav-link' . (!empty($item['active']) ? ' active' : ''),
                                ]
                            ) ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </nav>
                <?php if (!empty($this->params['breadcrumbs'])): ?>
                    <div class="mt-4 small text-muted px-2 border-top pt-2">
                        <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
                    </div>
                <?php endif ?>
            </aside>
            <div class="col-md-9 col-lg-10 py-3">
                <?= Alert::widget() ?>
                <?= $content ?>
            </div>
        </div>
    </div>
</main>

<footer id="footer" class="mt-auto py-2 bg-light">
    <?php
    $mkt = Yii::$container->get(app\services\MarketStatusService::class)->footerSummary();
    ?>
    <div class="container-fluid text-center text-muted small">
        US Stock Volume Analyzer &copy; <?= date('Y') ?> — <?= Yii::powered() ?>
        <span class="ms-2">•</span>
        Provider: <?= yii\helpers\Html::encode($mkt['providerLabel']) ?>
        <?= $mkt['providerConfigured']
            ? '<span class="text-success">✓</span>'
            : '<span class="text-danger">✗</span>' ?>
        <span class="ms-2">•</span>
        Data: <?= $mkt['lastDataAt'] ? Yii::$app->formatter->asRelativeTime($mkt['lastDataAt']) : '—' ?>
        <span class="ms-2">•</span>
        Sinyal: <?= yii\helpers\Html::encode((string) ($mkt['lastSignalDate'] ?? '—')) ?>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

