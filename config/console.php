<?php

ini_set('memory_limit', '512M');

$params = require __DIR__ . '/params.php';
$shared = require __DIR__ . '/shared.php';
$db = $shared['db'];
$queue = $shared['queue'];

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', \app\components\SettingsBootstrap::class],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'formatter' => [
            'class' => \app\components\Formatter::class,
        ],
        'queue' => $queue,
        'marketData' => [
            'class' => \app\services\market\DataProviderFactory::class,
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default (dev). Production: konfigurasi SMTP transport.
            'useFileTransport' => true,
        ],
        'log' => [
            // Target log dibangun lalu difilter agar tidak ada entri null
            // (lihat errorAlertTarget di bawah — null bila ERROR_ALERT_EMAIL kosong).
            'targets' => array_values(array_filter([
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/app.log',
                    'maxLogFiles' => 20,
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['info'],
                    'categories' => ['app\services\*'],
                    'logFile' => '@runtime/logs/services.log',
                    'maxLogFiles' => 20,
                    'logVars' => [],
                ],
                // Error alerting (Modul 15.5) — notifikasi email saat error/warning.
                // Diaktifkan bila env ERROR_ALERT_EMAIL di-set (mis. di docker-compose).
                \app\helpers\Log::errorAlertTarget(),
            ])),
        ],
        'db' => $db,
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
    // configuration adjustments for 'dev' environment
    // requires version `2.1.21` of yii2-debug module
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
