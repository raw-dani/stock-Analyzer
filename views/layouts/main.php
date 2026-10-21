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
$ctrlId = Yii::$app->controller?->id;
$actionId = Yii::$app->controller?->action?->id;
$currentUser = Yii::$app->user->identity;
$isAdmin = $currentUser !== null && $currentUser->isAdmin();

$systemItems = [];
if ($isAdmin) {
    $systemItems[] = ['label' => \app\helpers\SvgHelper::icon('gear', ['class' => 'me-2']) . 'Settings', 'url' => ['/settings/index'], 'active' => $ctrlId === 'settings'];
    $systemItems[] = ['label' => \app\helpers\SvgHelper::icon('people', ['class' => 'me-2']) . 'User Management', 'url' => ['/user/index'], 'active' => $ctrlId === 'user', 'badge' => 'Admin'];
}

$sideMenuGroups = [
    'Overview' => [
        ['label' => \app\helpers\SvgHelper::icon('speedometer2', ['class' => 'me-2']) . 'Dashboard', 'url' => ['/dashboard/index'], 'active' => $ctrlId === 'dashboard'],
        ['label' => \app\helpers\SvgHelper::icon('briefcase', ['class' => 'me-2']) . 'Portofolio & Averaging', 'url' => ['/portfolio/index'], 'active' => $ctrlId === 'portfolio', 'badge' => 'New'],
        ['label' => \app\helpers\SvgHelper::icon('info-circle', ['class' => 'me-2']) . 'Tentang Aplikasi', 'url' => ['/site/about'], 'active' => $ctrlId === 'site' && $actionId === 'about'],
    ],
    'Crypto Market (24/7)' => [
        ['label' => '<i class="bi bi-search me-2 text-warning fs-6"></i> Volume Scanner & Hub', 'url' => ['/crypto/index'], 'active' => $ctrlId === 'crypto' && in_array($actionId, ['index', 'view'], true), 'badge' => '24/7'],
        ['label' => '<i class="bi bi-bullseye me-2 text-primary fs-6"></i> Confluence Strategy', 'url' => ['/crypto/confluence'], 'active' => $ctrlId === 'crypto' && $actionId === 'confluence', 'badge' => 'PRO'],
        ['label' => '<i class="bi bi-rocket-takeoff me-2 text-success fs-6"></i> Bullish Patterns', 'url' => ['/crypto/bullish-patterns'], 'active' => $ctrlId === 'crypto' && $actionId === 'bullish-patterns', 'badge' => 'New'],
    ],
    'Scanners & Patterns' => [
        ['label' => \app\helpers\SvgHelper::icon('bullseye', ['class' => 'me-2']) . 'Confluence Strategy', 'url' => ['/strategy-confluence/index'], 'active' => $ctrlId === 'strategy-confluence', 'badge' => 'PRO'],
        ['label' => \app\helpers\SvgHelper::icon('rocket-takeoff', ['class' => 'me-2']) . 'Bullish Patterns', 'url' => ['/bullish-pattern/index'], 'active' => $ctrlId === 'bullish-pattern', 'badge' => 'New'],
        ['label' => \app\helpers\SvgHelper::icon('graph-down', ['class' => 'me-2']) . 'Double Bottom (Core)', 'url' => ['/double-bottom/index'], 'active' => $ctrlId === 'double-bottom', 'badge' => 'Core'],
        ['label' => \app\helpers\SvgHelper::icon('triangle-half', ['class' => 'me-2']) . 'Triple Bottom Scanner', 'url' => ['/triple-bottom/index'], 'active' => $ctrlId === 'triple-bottom', 'badge' => 'New'],
        ['label' => \app\helpers\SvgHelper::icon('activity', ['class' => 'me-2']) . 'RSI Strategy & Signals', 'url' => ['/rsi-double-bottom/index'], 'active' => str_starts_with($ctrlId ?? '', 'rsi-double-bottom')],
        ['label' => \app\helpers\SvgHelper::icon('search', ['class' => 'me-2']) . 'Volume Scanner', 'url' => ['/scanner/index'], 'active' => $ctrlId === 'scanner'],
        ['label' => \app\helpers\SvgHelper::icon('graph-up', ['class' => 'me-2']) . 'Moving Average Signals', 'url' => ['/moving-average/index'], 'active' => $ctrlId === 'moving-average', 'badge' => 'New'],
        ['label' => \app\helpers\SvgHelper::icon('layers', ['class' => 'me-2']) . 'Fibonacci Levels', 'url' => ['/fibonacci/index'], 'active' => $ctrlId === 'fibonacci', 'badge' => 'New'],
        ['label' => \app\helpers\SvgHelper::icon('bar-chart-line', ['class' => 'me-2']) . 'Candlestick Scanner', 'url' => ['/candlestick/index'], 'active' => $ctrlId === 'candlestick', 'badge' => 'New'],
    ],
];

if (!empty($systemItems)) {
    $sideMenuGroups['Administration'] = $systemItems;
}
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php $this->head() ?>
    <style>
        body { padding-top: 58px; }
        .sidebar { height: calc(100vh - 58px); position: sticky; top: 58px; overflow-y: auto; border-right: 1px solid #dee2e6; background-color: #f8f9fa; }
        .sidebar .nav-link { color: #495057; border-radius: .375rem; font-weight: 500; padding: .5rem .75rem; display: flex; align-items: center; }
        .sidebar .nav-link svg { flex-shrink: 0; }
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
        'brandLabel' => \app\helpers\SvgHelper::icon('graph-up-arrow', ['class' => 'me-2 text-primary']) . 'Stock Volume Analyzer',
        'brandUrl' => Yii::$app->homeUrl,
        'options' => ['class' => 'navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm']
    ]);
    
    // Top navigation: jika user login tampilkan semua menu utama, jika guest hanya tampilkan Login / Register / About
    $topNavItems = [];
    if (!Yii::$app->user->isGuest) {
        $topNavItems = [
            ['label' => \app\helpers\SvgHelper::icon('speedometer2', ['class' => 'me-1']) . ' Dashboard', 'url' => ['/dashboard/index'], 'active' => $ctrlId === 'dashboard', 'encode' => false],
            [
                'label' => '<i class="bi bi-currency-bitcoin text-warning me-1"></i> Crypto <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">24/7</span>',
                'encode' => false,
                'active' => $ctrlId === 'crypto',
                'items' => [
                    ['label' => '<i class="bi bi-search text-warning me-2"></i> Volume Scanner & Hub', 'url' => ['/crypto/index'], 'encode' => false],
                    '<div class="dropdown-divider"></div>',
                    ['label' => '<i class="bi bi-bullseye text-primary me-2"></i> Confluence Strategy (MA, RSI, MACD, Fib)', 'url' => ['/crypto/confluence'], 'encode' => false],
                    ['label' => '<i class="bi bi-rocket-takeoff text-success me-2"></i> Bullish Continuation & Breakouts', 'url' => ['/crypto/bullish-patterns'], 'encode' => false],
                ],
            ],
            ['label' => \app\helpers\SvgHelper::icon('briefcase', ['class' => 'me-1']) . ' Portofolio', 'url' => ['/portfolio/index'], 'active' => $ctrlId === 'portfolio', 'encode' => false],
            [
                'label' => \app\helpers\SvgHelper::icon('filter-square', ['class' => 'me-1']) . ' Scanners & Patterns',
                'encode' => false,
                'active' => in_array($ctrlId, ['strategy-confluence', 'bullish-pattern', 'double-bottom', 'triple-bottom', 'rsi-double-bottom', 'scanner', 'moving-average', 'fibonacci', 'candlestick'], true),
                'items' => [
                    ['label' => \app\helpers\SvgHelper::icon('bullseye', ['class' => 'me-2 text-warning']) . 'Confluence Strategy (PRO)', 'url' => ['/strategy-confluence/index'], 'encode' => false],
                    '<div class="dropdown-divider"></div>',
                    ['label' => \app\helpers\SvgHelper::icon('rocket-takeoff', ['class' => 'me-2 text-success']) . 'Bullish Breakout Patterns', 'url' => ['/bullish-pattern/index'], 'encode' => false],
                    ['label' => \app\helpers\SvgHelper::icon('graph-down', ['class' => 'me-2 text-primary']) . 'Double Bottom Scanner (Core)', 'url' => ['/double-bottom/index'], 'encode' => false],
                    ['label' => \app\helpers\SvgHelper::icon('triangle-half', ['class' => 'me-2 text-info']) . 'Triple Bottom Scanner', 'url' => ['/triple-bottom/index'], 'encode' => false],
                    ['label' => \app\helpers\SvgHelper::icon('activity', ['class' => 'me-2 text-danger']) . 'RSI Strategy & Signals', 'url' => ['/rsi-double-bottom/index'], 'encode' => false],
                    '<div class="dropdown-divider"></div>',
                    ['label' => \app\helpers\SvgHelper::icon('search', ['class' => 'me-2 text-primary']) . 'Volume Scanner', 'url' => ['/scanner/index'], 'encode' => false],
                    ['label' => \app\helpers\SvgHelper::icon('graph-up', ['class' => 'me-2 text-success']) . 'Moving Average Signals', 'url' => ['/moving-average/index'], 'encode' => false],
                    ['label' => \app\helpers\SvgHelper::icon('layers', ['class' => 'me-2 text-warning']) . 'Fibonacci Levels', 'url' => ['/fibonacci/index'], 'encode' => false],
                    ['label' => \app\helpers\SvgHelper::icon('bar-chart-line', ['class' => 'me-2 text-info']) . 'Candlestick Scanner', 'url' => ['/candlestick/index'], 'encode' => false],
                ],
            ],
        ];

        if ($isAdmin) {
            $topNavItems[] = [
                'label' => \app\helpers\SvgHelper::icon('gear', ['class' => 'me-1']) . ' Admin',
                'encode' => false,
                'active' => in_array($ctrlId, ['settings', 'user'], true),
                'items' => [
                    ['label' => \app\helpers\SvgHelper::icon('gear', ['class' => 'me-2 text-secondary']) . 'Settings', 'url' => ['/settings/index'], 'encode' => false],
                    ['label' => \app\helpers\SvgHelper::icon('people', ['class' => 'me-2 text-primary']) . 'User Management', 'url' => ['/user/index'], 'encode' => false],
                ],
            ];
        }

        $roleBadge = $isAdmin ? ' <span class="badge bg-danger ms-1">Admin</span>' : ' <span class="badge bg-primary ms-1">User</span>';
        $topNavItems[] = [
            'label' => \app\helpers\SvgHelper::icon('person-circle', ['class' => 'me-1']) . ' ' . Html::encode($currentUser->username) . $roleBadge,
            'encode' => false,
            'items' => [
                ['label' => \app\helpers\SvgHelper::icon('person-vcard', ['class' => 'me-2 text-primary']) . 'Profil Saya & Ganti Password', 'url' => ['/site/profile'], 'encode' => false],
                ['label' => \app\helpers\SvgHelper::icon('info-circle', ['class' => 'me-2 text-info']) . 'Tentang Aplikasi & Developer', 'url' => ['/site/about'], 'encode' => false],
                $isAdmin ? ['label' => \app\helpers\SvgHelper::icon('people', ['class' => 'me-2 text-primary']) . 'User Management', 'url' => ['/user/index'], 'encode' => false] : '',
                '<div class="dropdown-divider"></div>',
                '<li class="px-2">'
                    . Html::beginForm(['/site/logout'])
                    . Html::submitButton(
                        \app\helpers\SvgHelper::icon('box-arrow-right', ['class' => 'me-1 text-danger']) . ' Keluar (Logout)',
                        ['class' => 'dropdown-item text-danger rounded-2 py-1']
                    )
                    . Html::endForm()
                    . '</li>',
            ],
        ];
    } else {
        $topNavItems[] = ['label' => \app\helpers\SvgHelper::icon('pencil-square', ['class' => 'me-1']) . ' Register', 'url' => ['/site/register'], 'encode' => false];
        $topNavItems[] = ['label' => \app\helpers\SvgHelper::icon('key', ['class' => 'me-1']) . ' Login', 'url' => ['/site/login'], 'encode' => false];
    }

    echo Nav::widget([
        'options' => ['class' => 'navbar-nav ms-auto align-items-lg-center'],
        'items' => $topNavItems,
    ]);
    NavBar::end();
    ?>
</header>

<main id="main" class="flex-shrink-0" role="main">
    <div class="container-fluid">
        <?php 
        $isGuest = Yii::$app->user->isGuest;
        $isAuthPage = ($ctrlId === 'site' && in_array(Yii::$app->controller?->action?->id ?? '', ['login', 'register'], true));
        $hideSidebar = $isGuest || $isAuthPage;
        ?>
        <?php if ($hideSidebar): ?>
            <div class="py-2">
                <?= Alert::widget() ?>
                <?= $content ?>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <aside id="sidebar-wrapper" class="col-md-3 col-lg-2 d-none d-md-block pt-3 sidebar">
                    <div class="d-flex align-items-center justify-content-between px-2 mb-2 pb-2 border-bottom">
                        <span class="small fw-bold text-muted text-uppercase tracking-wider">Navigasi Fitur</span>
                        <button type="button" 
                                id="btn-sidebar-collapse" 
                                class="btn btn-sm btn-light border-0 text-muted p-1 rounded-2 shadow-none" 
                                title="Sembunyikan Menu Sidebar (Ctrl+B)" 
                                onclick="toggleSidebar()">
                            <?= \app\helpers\SvgHelper::icon('layout-sidebar-inset', ['class' => 'fs-6']) ?>
                        </button>
                    </div>
                    <nav class="nav flex-column gap-1">
                        <?php foreach ($sideMenuGroups as $groupTitle => $items): ?>
                            <div class="sidebar-heading"><?= Html::encode($groupTitle) ?></div>
                            <?php foreach ($items as $item): ?>
                                <?= Html::a(
                                    '<span class="d-flex align-items-center flex-grow-1">' . $item['label'] . '</span>' . (!empty($item['badge']) ? ' <span class="badge badge-core ms-auto">' . $item['badge'] . '</span>' : ''),
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
                <div id="main-content-col" class="col-md-9 col-lg-10 py-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <button type="button" 
                                id="btn-sidebar-expand" 
                                class="sidebar-toggle-btn shadow-sm" 
                                title="Tampilkan Menu Sidebar" 
                                onclick="toggleSidebar()" 
                                style="display: none;">
                            <?= \app\helpers\SvgHelper::icon('layout-sidebar', ['class' => 'fs-5']) ?>
                        </button>
                    </div>
                    <?= Alert::widget() ?>
                    <?= $content ?>
                </div>
            </div>
        <?php endif; ?>
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
        <span class="ms-2">•</span>
        <?= Html::a(\app\helpers\SvgHelper::icon('info-circle', ['class' => 'me-1']) . 'About', ['/site/about'], ['class' => 'text-decoration-none text-muted']) ?>
    </div>
</footer>

<script>
function toggleSidebar() {
    var isCollapsed = document.body.classList.toggle('sidebar-collapsed');
    localStorage.setItem('stock_analyzer_sidebar_collapsed', isCollapsed ? '1' : '0');
    updateSidebarToggleUI(isCollapsed);
}

function updateSidebarToggleUI(isCollapsed) {
    var expandBtn = document.getElementById('btn-sidebar-expand');
    if (expandBtn) {
        expandBtn.style.display = isCollapsed ? 'inline-flex' : 'none';
    }
}

// Restore user sidebar preference
(function() {
    var savedState = localStorage.getItem('stock_analyzer_sidebar_collapsed');
    if (savedState === '1') {
        document.body.classList.add('sidebar-collapsed');
        updateSidebarToggleUI(true);
    }
    
    // Shortcut Ctrl+B or Cmd+B to toggle sidebar
    window.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
            e.preventDefault();
            toggleSidebar();
        }
    });
})();
</script>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

