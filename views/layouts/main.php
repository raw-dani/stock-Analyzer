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

// menu sidebar (task 5.1)
$sideMenu = [
    ['label' => '📊 Dashboard', 'url' => ['/dashboard/index'], 'active' => Yii::$app->controller->id === 'dashboard'],
    ['label' => '🔍 Scanner', 'url' => ['/scanner/index'], 'active' => Yii::$app->controller->id === 'scanner'],
    ['label' => '📉 Double Bottom', 'url' => ['/double-bottom/index'], 'active' => Yii::$app->controller->id === 'double-bottom'],
    ['label' => '📉 RSI Double Bottom', 'url' => ['/rsi-double-bottom/index'], 'active' => str_starts_with(Yii::$app->controller->id, 'rsi-double-bottom')],
    ['label' => '🗺️ Sectors', 'url' => ['/sector/index'], 'active' => Yii::$app->controller->id === 'sector'],
    ['label' => '⭐ Watchlist', 'url' => ['/watchlist/index'], 'active' => Yii::$app->controller->id === 'watchlist'],
    ['label' => '📈 Signals', 'url' => ['/signal/index'], 'active' => Yii::$app->controller->id === 'signal'],
    ['label' => '🔔 Alerts', 'url' => ['/alert/index'], 'active' => Yii::$app->controller->id === 'alert'],
];
// Halaman settings khusus admin (RBAC Modul 14)
if (!Yii::$app->user->isGuest && Yii::$app->user->identity instanceof app\models\User && Yii::$app->user->identity->isAdmin()) {
    $sideMenu[] = ['label' => '⚙️ Settings', 'url' => ['/settings/index'], 'active' => Yii::$app->controller->id === 'settings'];
}
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
    <style>
        .sidebar { min-height: calc(100vh - 56px); border-right: 1px solid #dee2e6; }
        .sidebar .nav-link { color: #333; border-radius: .375rem; }
        .sidebar .nav-link.active { background: #0d6efd; color: #fff; }
        .kpi { font-size: 1.6rem; font-weight: 700; }
    </style>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<header id="header">
    <?php
    NavBar::begin([
        'brandLabel' => '📈 US Stock Volume Analyzer',
        'brandUrl' => Yii::$app->homeUrl,
        'options' => ['class' => 'navbar-expand-md navbar-dark bg-dark fixed-top']
    ]);
    echo Nav::widget([
        'options' => ['class' => 'navbar-nav ms-auto'],
        'items' => array_filter([
            Yii::$app->user->isGuest ? ['label' => 'Login', 'url' => ['/site/login']] : null,
            Yii::$app->user->isGuest ? ['label' => 'Register', 'url' => ['/site/register']] : null,
            !Yii::$app->user->isGuest
                ? '<li class="nav-item">'
                    . Html::beginForm(['/site/logout'])
                    . Html::submitButton(
                        'Logout (' . Yii::$app->user->identity->username . ')',
                        ['class' => 'nav-link btn btn-link logout']
                    )
                    . Html::endForm()
                    . '</li>'
                : null,
        ])
    ]);
    NavBar::end();
    ?>
</header>

<main id="main" class="flex-shrink-0" role="main">
    <div class="container-fluid">
        <div class="row">
            <aside class="col-md-2 col-lg-2 d-none d-md-block pt-3 sidebar">
                <nav class="nav flex-column gap-1">
                    <?php foreach ($sideMenu as $item): ?>
                        <?= Html::a($item['label'], $item['url'], [
                            'class' => 'nav-link' . (!empty($item['active']) ? ' active' : ''),
                        ]) ?>
                    <?php endforeach ?>
                </nav>
                <?php if (!empty($this->params['breadcrumbs'])): ?>
                    <div class="mt-3 small text-muted px-2">
                        <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
                    </div>
                <?php endif ?>
            </aside>
            <div class="col-md-10 col-lg-10 py-3">
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

