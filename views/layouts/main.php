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
    $systemItems[] = ['label' => \app\helpers\SvgHelper::icon('newspaper', ['class' => 'me-2 text-info']) . 'Kelola Blog', 'url' => ['/blog/admin'], 'active' => $ctrlId === 'blog' && in_array($actionId, ['admin', 'create', 'update']), 'badge' => 'Admin'];
    $systemItems[] = ['label' => \app\helpers\SvgHelper::icon('globe', ['class' => 'me-2 text-primary']) . 'SEO & Webmaster', 'url' => ['/blog/admin/settings'], 'active' => $ctrlId === 'blog' && $actionId === 'settings', 'badge' => 'Admin'];
    $systemItems[] = ['label' => \app\helpers\SvgHelper::icon('gear', ['class' => 'me-2']) . 'Settings', 'url' => ['/settings/index'], 'active' => $ctrlId === 'settings'];
    $systemItems[] = ['label' => \app\helpers\SvgHelper::icon('people', ['class' => 'me-2']) . 'User Management', 'url' => ['/user/index'], 'active' => $ctrlId === 'user', 'badge' => 'Admin'];
}

$sideMenuGroups = [
    'Overview' => [
        ['label' => \app\helpers\SvgHelper::icon('speedometer2', ['class' => 'me-2']) . 'Dashboard', 'url' => ['/dashboard/index'], 'active' => $ctrlId === 'dashboard'],
        ['label' => \app\helpers\SvgHelper::icon('newspaper', ['class' => 'me-2 text-info']) . 'Blog Info Saham', 'url' => ['/blog/index'], 'active' => $ctrlId === 'blog' && !in_array($actionId, ['admin', 'settings', 'create', 'update']), 'badge' => 'AI'],
        ['label' => \app\helpers\SvgHelper::icon('briefcase', ['class' => 'me-2']) . 'Portofolio & Averaging', 'url' => ['/portfolio/index'], 'active' => $ctrlId === 'portfolio'],
        ['label' => \app\helpers\SvgHelper::icon('info-circle', ['class' => 'me-2']) . 'Tentang Aplikasi', 'url' => ['/site/about'], 'active' => $ctrlId === 'site' && $actionId === 'about'],
    ],
    'Crypto Market (24/7)' => [
        ['label' => '<i class="bi bi-search me-2 text-warning fs-6"></i> Volume Scanner & Hub', 'url' => ['/crypto/index'], 'active' => $ctrlId === 'crypto' && in_array($actionId, ['index', 'view'], true), 'badge' => '24/7'],
        ['label' => '<i class="bi bi-bullseye me-2 text-primary fs-6"></i> Confluence Strategy', 'url' => ['/crypto/confluence'], 'active' => $ctrlId === 'crypto' && $actionId === 'confluence', 'badge' => 'PRO'],
        ['label' => '<i class="bi bi-rocket-takeoff me-2 text-success fs-6"></i> Bullish Patterns', 'url' => ['/crypto/bullish-patterns'], 'active' => $ctrlId === 'crypto' && $actionId === 'bullish-patterns'],
    ],
    'Scanners & Patterns' => [
        ['label' => \app\helpers\SvgHelper::icon('bullseye', ['class' => 'me-2']) . 'Confluence Strategy', 'url' => ['/strategy-confluence/index'], 'active' => $ctrlId === 'strategy-confluence', 'badge' => 'PRO'],
        ['label' => \app\helpers\SvgHelper::icon('rocket-takeoff', ['class' => 'me-2']) . 'Bullish Patterns', 'url' => ['/bullish-pattern/index'], 'active' => $ctrlId === 'bullish-pattern'],
        ['label' => \app\helpers\SvgHelper::icon('graph-down', ['class' => 'me-2']) . 'Double Bottom (Core)', 'url' => ['/double-bottom/index'], 'active' => $ctrlId === 'double-bottom', 'badge' => 'Core'],
        ['label' => \app\helpers\SvgHelper::icon('triangle-half', ['class' => 'me-2']) . 'Triple Bottom Scanner', 'url' => ['/triple-bottom/index'], 'active' => $ctrlId === 'triple-bottom'],
        ['label' => \app\helpers\SvgHelper::icon('activity', ['class' => 'me-2']) . 'RSI Strategy & Signals', 'url' => ['/rsi-double-bottom/index'], 'active' => str_starts_with($ctrlId ?? '', 'rsi-double-bottom')],
        ['label' => \app\helpers\SvgHelper::icon('search', ['class' => 'me-2']) . 'Volume Scanner', 'url' => ['/scanner/index'], 'active' => $ctrlId === 'scanner'],
        ['label' => \app\helpers\SvgHelper::icon('graph-up', ['class' => 'me-2']) . 'Moving Average Signals', 'url' => ['/moving-average/index'], 'active' => $ctrlId === 'moving-average'],
        ['label' => \app\helpers\SvgHelper::icon('bounding-box', ['class' => 'me-2']) . 'Bollinger Bands Strategy', 'url' => ['/bollinger-band/index'], 'active' => $ctrlId === 'bollinger-band'],
        ['label' => \app\helpers\SvgHelper::icon('layers', ['class' => 'me-2']) . 'Fibonacci Levels', 'url' => ['/fibonacci/index'], 'active' => $ctrlId === 'fibonacci'],
        ['label' => \app\helpers\SvgHelper::icon('bar-chart-line', ['class' => 'me-2']) . 'Candlestick Scanner', 'url' => ['/candlestick/index'], 'active' => $ctrlId === 'candlestick'],
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
    <?= \app\helpers\TrackingHelper::renderHeadScripts() ?>
    <?php $this->head() ?>
    <style>
        body { padding-top: 60px; }
        .sidebar { height: calc(100vh - 60px); position: sticky; top: 60px; overflow-y: auto; border-right: 1px solid #dee2e6; background-color: #f8f9fa; }
        .sidebar .nav-link { color: #495057; border-radius: .375rem; font-weight: 500; padding: .5rem .75rem; display: flex; align-items: center; }
        .sidebar .nav-link svg { flex-shrink: 0; }
        .sidebar .nav-link:hover { background: #e9ecef; color: #0d6efd; }
        .sidebar .nav-link.active { background: #0d6efd; color: #fff; }
        .sidebar-heading { font-size: .72rem; font-weight: 700; text-transform: uppercase; color: #6c757d; letter-spacing: .05rem; padding: .6rem .75rem .25rem; }
        .kpi { font-size: 1.6rem; font-weight: 700; }
        .badge-core { background: linear-gradient(135deg, #0d6efd, #0dcaf0); color: #fff; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; }

        /* Sleek Modern Dark Navbar */
        .navbar-custom {
            background: linear-gradient(180deg, #0f172a 0%, #0a0f1d 100%) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
        .brand-icon-box {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
            border-radius: 8px;
            color: #fff;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.4);
        }
        .brand-text {
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: -0.02em;
        }
        .text-primary-gradient {
            background: linear-gradient(135deg, #38bdf8 0%, #818cf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .user-avatar-badge {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #6366f1);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        }
        .navbar-custom .nav-link {
            font-size: 0.88rem;
            font-weight: 500;
            color: #cbd5e1 !important;
            padding: 0.45rem 0.85rem !important;
            border-radius: 8px;
            transition: all 0.18s ease-in-out;
            display: inline-flex;
            align-items: center;
        }
        .navbar-custom .nav-link:hover {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.08);
        }
        .navbar-custom .nav-link.active {
            color: #38bdf8 !important;
            background: rgba(56, 189, 248, 0.12);
            font-weight: 600;
        }
        /* Enhanced Dropdown Styling */
        .navbar-custom .dropdown-menu {
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
            padding: 0.5rem;
            min-width: 270px;
            margin-top: 0.5rem;
        }
        .navbar-custom .dropdown-menu-scrollable {
            max-height: calc(100vh - 85px);
            overflow-y: auto;
        }
        .navbar-custom .dropdown-header {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.45rem 0.75rem 0.25rem;
        }
        .navbar-custom .dropdown-item {
            font-size: 0.84rem;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 0.45rem 0.75rem;
            transition: all 0.15s ease-in-out;
        }
        .navbar-custom .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #38bdf8;
            transform: translateX(2px);
        }
        .navbar-custom .dropdown-item.active {
            background: #0d6efd;
            color: #ffffff;
        }
        .navbar-custom .dropdown-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin: 0.4rem 0;
        }
    </style>
</head>
<body class="d-flex flex-column h-100">
<?= \app\helpers\TrackingHelper::renderBodyStart() ?>
<?php $this->beginBody() ?>

<header id="header">
    <?php
    NavBar::begin([
        'brandLabel' => '<div class="d-flex align-items-center gap-2 text-decoration-none">'
            . '<span class="brand-icon-box"><i class="bi bi-graph-up-arrow"></i></span>'
            . '<span class="brand-text text-white">Stock <span class="text-primary-gradient">Volume Analyzer</span></span>'
            . '</div>',
        'brandUrl' => Yii::$app->homeUrl,
        'options' => ['class' => 'navbar-expand-lg navbar-dark navbar-custom fixed-top']
    ]);
    
    // Top navigation: jika user login tampilkan struktur modern terpadu
    $topNavItems = [];
    if (!Yii::$app->user->isGuest) {
        $isMarketActive = in_array($ctrlId, [
            'strategy-confluence', 'bullish-pattern', 'double-bottom', 'triple-bottom', 
            'rsi-double-bottom', 'scanner', 'moving-average', 'bollinger-band', 
            'fibonacci', 'candlestick', 'crypto'
        ], true);

        // Grouped Items untuk "Pasar & Scanner"
        $marketItems = [
            '<div class="dropdown-header text-primary fw-bold d-flex align-items-center gap-1"><i class="bi bi-graph-up"></i> PASAR SAHAM US</div>',
            ['label' => '<i class="bi bi-bullseye text-warning me-2"></i> Confluence Strategy (PRO)', 'url' => ['/strategy-confluence/index'], 'encode' => false],
            ['label' => '<i class="bi bi-rocket-takeoff text-success me-2"></i> Bullish Breakout Patterns', 'url' => ['/bullish-pattern/index'], 'encode' => false],
            ['label' => '<i class="bi bi-graph-down text-primary me-2"></i> Double Bottom Scanner (Core)', 'url' => ['/double-bottom/index'], 'encode' => false],
            ['label' => '<i class="bi bi-triangle-half text-info me-2"></i> Triple Bottom Scanner', 'url' => ['/triple-bottom/index'], 'encode' => false],
            ['label' => '<i class="bi bi-activity text-danger me-2"></i> RSI Strategy & Signals', 'url' => ['/rsi-double-bottom/index'], 'encode' => false],
            ['label' => '<i class="bi bi-search text-primary me-2"></i> Volume Scanner', 'url' => ['/scanner/index'], 'encode' => false],
            ['label' => '<i class="bi bi-graph-up text-success me-2"></i> Moving Average Signals', 'url' => ['/moving-average/index'], 'encode' => false],
            ['label' => '<i class="bi bi-bounding-box text-info me-2"></i> Bollinger Bands Strategy', 'url' => ['/bollinger-band/index'], 'encode' => false],
            ['label' => '<i class="bi bi-layers text-warning me-2"></i> Fibonacci Levels', 'url' => ['/fibonacci/index'], 'encode' => false],
            ['label' => '<i class="bi bi-bar-chart-line text-info me-2"></i> Candlestick Scanner', 'url' => ['/candlestick/index'], 'encode' => false],
            '<div class="dropdown-divider"></div>',
            '<div class="dropdown-header text-warning fw-bold d-flex align-items-center gap-1"><i class="bi bi-currency-bitcoin"></i> PASAR KRIPTO 24/7 <span class="badge bg-warning text-dark ms-auto" style="font-size:0.6rem;">LIVE</span></div>',
            ['label' => '<i class="bi bi-search text-warning me-2"></i> Volume Scanner & Hub', 'url' => ['/crypto/index'], 'encode' => false],
            ['label' => '<i class="bi bi-bullseye text-primary me-2"></i> Confluence Strategy (MA, RSI, Fib)', 'url' => ['/crypto/confluence'], 'encode' => false],
            ['label' => '<i class="bi bi-rocket-takeoff text-success me-2"></i> Bullish Continuation & Breakouts', 'url' => ['/crypto/bullish-patterns'], 'encode' => false],
        ];

        $topNavItems = [
            [
                'label' => '<i class="bi bi-speedometer2 me-1 text-primary"></i> Dashboard',
                'url' => ['/dashboard/index'],
                'active' => $ctrlId === 'dashboard',
                'encode' => false,
            ],
            [
                'label' => '<i class="bi bi-sliders me-1 text-info"></i> Pasar & Scanner <i class="bi bi-chevron-down ms-1 text-white-50 small" style="font-size:0.65rem;"></i>',
                'encode' => false,
                'active' => $isMarketActive,
                'dropdownOptions' => ['class' => 'dropdown-menu shadow-lg border-0 dropdown-menu-scrollable'],
                'items' => $marketItems,
            ],
            [
                'label' => '<i class="bi bi-briefcase me-1 text-warning"></i> Portofolio',
                'url' => ['/portfolio/index'],
                'active' => $ctrlId === 'portfolio',
                'encode' => false,
            ],
            [
                'label' => '<i class="bi bi-newspaper me-1 text-info"></i> Blog Info <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-25 ms-1" style="font-size:0.65rem;">AI</span>',
                'url' => ['/blog/index'],
                'active' => $ctrlId === 'blog' && !in_array($actionId, ['admin', 'settings', 'create', 'update']),
                'encode' => false,
            ],
        ];

        // User & Admin Hub Menu
        $firstLetter = strtoupper(substr((string) ($currentUser->username ?? 'U'), 0, 1));
        $roleClass = $isAdmin ? 'bg-danger' : 'bg-primary';
        $roleTitle = $isAdmin ? 'Admin' : 'User';

        $userMenuItems = [
            '<div class="px-3 py-2 border-bottom mb-1 bg-dark bg-opacity-25">'
                . '<div class="d-flex align-items-center gap-2">'
                    . '<span class="user-avatar-badge">' . Html::encode($firstLetter) . '</span>'
                    . '<div class="overflow-hidden">'
                        . '<div class="fw-bold text-white text-truncate small" style="max-width:160px;">' . Html::encode($currentUser->username) . '</div>'
                        . '<span class="badge ' . $roleClass . ' bg-opacity-75" style="font-size:0.65rem;">' . $roleTitle . '</span>'
                    . '</div>'
                . '</div>'
            . '</div>',
        ];

        if ($isAdmin) {
            $userMenuItems[] = '<div class="dropdown-header text-info fw-bold"><i class="bi bi-shield-lock me-1"></i> ADMINISTRASI SISTEM</div>';
            $userMenuItems[] = ['label' => '<i class="bi bi-newspaper text-info me-2"></i> Kelola Blog & Artikel', 'url' => ['/blog/admin'], 'encode' => false];
            $userMenuItems[] = ['label' => '<i class="bi bi-globe text-primary me-2"></i> Pengaturan SEO & Webmaster', 'url' => ['/blog/admin/settings'], 'encode' => false];
            $userMenuItems[] = ['label' => '<i class="bi bi-gear text-secondary me-2"></i> Settings Konfigurasi', 'url' => ['/settings/index'], 'encode' => false];
            $userMenuItems[] = ['label' => '<i class="bi bi-people text-primary me-2"></i> User Management', 'url' => ['/user/index'], 'encode' => false];
            $userMenuItems[] = '<div class="dropdown-divider"></div>';
        }

        $userMenuItems[] = '<div class="dropdown-header text-muted fw-bold"><i class="bi bi-person me-1"></i> AKUN SAYA</div>';
        $userMenuItems[] = ['label' => '<i class="bi bi-person-vcard text-primary me-2"></i> Profil & Ganti Password', 'url' => ['/site/profile'], 'encode' => false];
        $userMenuItems[] = ['label' => '<i class="bi bi-info-circle text-info me-2"></i> Tentang Aplikasi', 'url' => ['/site/about'], 'encode' => false];
        $userMenuItems[] = '<div class="dropdown-divider"></div>';

        // Logout via POST data-method (cleanly inside dropdown, no leaking form button)
        $userMenuItems[] = [
            'label' => '<i class="bi bi-box-arrow-right me-2 text-danger"></i> <span class="text-danger fw-semibold">Keluar (Logout)</span>',
            'url' => ['/site/logout'],
            'linkOptions' => [
                'data-method' => 'post',
                'class' => 'dropdown-item d-flex align-items-center',
            ],
            'encode' => false,
        ];

        $topNavItems[] = [
            'label' => '<span class="user-avatar-badge me-1">' . Html::encode($firstLetter) . '</span> ' 
                     . Html::encode($currentUser->username) 
                     . ' <span class="badge ' . $roleClass . ' ms-1" style="font-size:0.65rem;">' . $roleTitle . '</span>'
                     . ' <i class="bi bi-chevron-down ms-1 text-white-50 small" style="font-size:0.65rem;"></i>',
            'encode' => false,
            'dropdownOptions' => ['class' => 'dropdown-menu dropdown-menu-end shadow-lg border-0'],
            'items' => $userMenuItems,
        ];
    } else {
        $topNavItems[] = ['label' => '<i class="bi bi-newspaper me-1 text-info"></i> Blog Info Saham', 'url' => ['/blog/index'], 'active' => $ctrlId === 'blog', 'encode' => false];
        $topNavItems[] = ['label' => '<i class="bi bi-pencil-square me-1"></i> Register', 'url' => ['/site/register'], 'encode' => false];
        $topNavItems[] = ['label' => '<i class="bi bi-key me-1"></i> Login', 'url' => ['/site/login'], 'encode' => false];
    }

    echo Nav::widget([
        'options' => ['class' => 'navbar-nav ms-auto align-items-lg-center gap-1'],
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

<footer id="footer" class="mt-auto bg-white border-top text-muted">
    <?php
    $mkt = Yii::$container->get(app\services\MarketStatusService::class)->footerSummary();
    ?>
    <div class="container-fluid py-4 px-md-4">
        <div class="row g-4 justify-content-between">
            <!-- Kolom 1: Brand & Powered by -->
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="brand-icon-box" style="width: 28px; height: 28px; font-size: 0.85rem;">
                        <i class="bi bi-graph-up-arrow"></i>
                    </span>
                    <span class="fw-bold text-dark fs-6 tracking-tight">Stock Volume Analyzer</span>
                </div>
                <p class="small text-muted mb-3" style="max-width: 360px; line-height: 1.6;">
                    Platform intelijen kuantitatif pasar modal US dan Crypto 24/7. Menyajikan deteksi anomali volume akumulasi, konfluensi indikator teknikal presisi, dan kalkulator trading plan terstruktur.
                </p>
                <div class="small d-flex align-items-center gap-1">
                    <span class="text-secondary">Powered by</span> 
                    <a href="https://gmteknologi.com" target="_blank" rel="noopener noreferrer" class="fw-bold text-primary text-decoration-none">
                        gmteknologi.com <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.7rem;"></i>
                    </a>
                </div>
            </div>

            <!-- Kolom 2: Menu Scanner Saham -->
            <div class="col-lg-2 col-md-3 col-6">
                <h6 class="fw-bold text-dark text-uppercase small mb-3 tracking-wider" style="font-size: 0.75rem;">Pasar Saham US</h6>
                <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                    <li><a href="<?= yii\helpers\Url::to(['/strategy-confluence/index']) ?>" class="text-decoration-none text-muted hover-primary">Confluence Strategy</a></li>
                    <li><a href="<?= yii\helpers\Url::to(['/bullish-pattern/index']) ?>" class="text-decoration-none text-muted hover-primary">Bullish Breakouts</a></li>
                    <li><a href="<?= yii\helpers\Url::to(['/double-bottom/index']) ?>" class="text-decoration-none text-muted hover-primary">Double Bottom Core</a></li>
                    <li><a href="<?= yii\helpers\Url::to(['/scanner/index']) ?>" class="text-decoration-none text-muted hover-primary">Volume &amp; RVOL Scanner</a></li>
                    <li><a href="<?= yii\helpers\Url::to(['/rsi-double-bottom/index']) ?>" class="text-decoration-none text-muted hover-primary">RSI &amp; Signals</a></li>
                </ul>
            </div>

            <!-- Kolom 3: Crypto & Riset AI -->
            <div class="col-lg-2 col-md-3 col-6">
                <h6 class="fw-bold text-dark text-uppercase small mb-3 tracking-wider" style="font-size: 0.75rem;">Crypto &amp; Riset</h6>
                <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                    <li><a href="<?= yii\helpers\Url::to(['/crypto/index']) ?>" class="text-decoration-none text-muted hover-primary"><i class="bi bi-currency-bitcoin text-warning me-1"></i> Crypto Hub 24/7</a></li>
                    <li><a href="<?= yii\helpers\Url::to(['/crypto/confluence']) ?>" class="text-decoration-none text-muted hover-primary">Crypto Confluence</a></li>
                    <li><a href="<?= yii\helpers\Url::to(['/portfolio/index']) ?>" class="text-decoration-none text-muted hover-primary">Portofolio &amp; DCA</a></li>
                    <li><a href="<?= yii\helpers\Url::to(['/blog/index']) ?>" class="text-decoration-none text-muted hover-primary"><i class="bi bi-newspaper text-info me-1"></i> Blog Info AI</a></li>
                    <li><a href="<?= yii\helpers\Url::to(['/site/about']) ?>" class="text-decoration-none text-muted hover-primary">Tentang Aplikasi</a></li>
                </ul>
            </div>

            <!-- Kolom 4: Status Pasar & Provider Data -->
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold text-dark text-uppercase small mb-3 tracking-wider" style="font-size: 0.75rem;">Status Sistem &amp; Feed</h6>
                <div class="card border border-light-subtle rounded-3 bg-light p-3 small shadow-none">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light-subtle">
                        <span class="text-muted">Data Provider:</span>
                        <span class="fw-semibold text-dark">
                            <?= yii\helpers\Html::encode($mkt['providerLabel']) ?>
                            <?= $mkt['providerConfigured'] ? '<i class="bi bi-check-circle-fill text-success ms-1"></i>' : '<i class="bi bi-x-circle-fill text-danger ms-1"></i>' ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light-subtle">
                        <span class="text-muted">Pembaruan:</span>
                        <span class="fw-medium text-dark"><?= $mkt['lastDataAt'] ? Yii::$app->formatter->asRelativeTime($mkt['lastDataAt']) : '—' ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Sinyal Terakhir:</span>
                        <span class="fw-medium text-dark"><?= yii\helpers\Html::encode((string) ($mkt['lastSignalDate'] ?? '—')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disclaimer & Copyright Bar -->
        <div class="border-top mt-4 pt-3">
            <div class="row align-items-center gy-2">
                <div class="col-md-7 small text-muted" style="font-size: 0.75rem; line-height: 1.5;">
                    <span class="fw-bold text-secondary">Disclaimer:</span> Seluruh data volume dan analisa teknikal disediakan semata-mata untuk tujuan riset dan edukasi kuantitatif, bukan rekomendasi investasi finansial langsung.
                </div>
                <div class="col-md-5 text-md-end small text-muted">
                    <span>&copy; <?= date('Y') ?> <strong>Stock Volume Analyzer</strong> &bull; Powered by <a href="https://gmteknologi.com" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-semibold text-primary">gmteknologi.com</a></span>
                </div>
            </div>
        </div>
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

<?= \app\helpers\TrackingHelper::renderBodyEnd() ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

