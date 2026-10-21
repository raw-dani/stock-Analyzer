<?php

ini_set('memory_limit', '512M');

$params = require __DIR__ . '/params.php';
$shared = require __DIR__ . '/shared.php';
$db = $shared['db'];
$queue = $shared['queue'];

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', \app\components\SettingsBootstrap::class],
    'on beforeRequest' => function ($event) {
        // Enforce login untuk semua halaman web/menu aplikasi
        // Pengecualian publik: login, register, captcha, error, monitoring health, dan REST API (api/)
        if (Yii::$app->has('user') && Yii::$app->user->isGuest) {
            $reqUrl = Yii::$app->request->url ?? '';
            $pathInfo = trim(Yii::$app->request->getPathInfo(), '/');
            $isPublic = ($pathInfo === '' || $pathInfo === 'site/index') ||
                        str_contains($reqUrl, 'site/login') ||
                        str_contains($reqUrl, 'site/register') ||
                        str_contains($reqUrl, 'site/captcha') ||
                        str_contains($reqUrl, 'site/error') ||
                        str_contains($reqUrl, 'health') ||
                        str_contains($reqUrl, 'api/');

            if (!$isPublic) {
                // Simpan URL yang dituju sebagai returnUrl jika bukan root
                Yii::$app->user->setReturnUrl(Yii::$app->request->url);
                Yii::$app->response->redirect(['/site/login'])->send();
                exit;
            }
        }
    },
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'C1GrSXlc54F01tB7cTQCvJxkXbmBeNDk',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'class' => \app\components\ApiErrorHandler::class,
            'errorAction' => 'site/error',
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
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
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
                // null bila ERROR_ALERT_EMAIL kosong → dibuang oleh array_filter.
                \app\helpers\Log::errorAlertTarget(),
            ])),
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'crypto/confluence' => 'crypto/confluence',
                'crypto/bullish-patterns' => 'crypto/bullish-patterns',
                'crypto/index' => 'crypto/index',
                'crypto/<symbol:[A-Za-z0-9]+>' => 'crypto/view',
                'crypto' => 'crypto/index',
                'double-bottom/<symbol:[A-Z.]+>' => 'double-bottom/detail',
                'double-bottom' => 'double-bottom/index',
                'stock/<symbol:[A-Z.]+>' => 'stock/view',
                'api/v1/stocks/<symbol:[A-Z0-9.\-]+>' => 'api/v1/stocks/view',
                'POST api/v1/<controller:[\w-]+>/<action:[\w-]+>' => 'api/v1/<controller>/<action>',
                'api/v1/<controller:[\w-]+>/<action:[\w-]+>' => 'api/v1/<controller>/<action>',
                'api/v1/<controller:[\w-]+>' => 'api/v1/<controller>/index',
            ],
        ],
    ],
    'params' => $params,
    'modules' => [
        'api' => [
            'class' => \app\modules\api\Module::class,
            'modules' => [
                'v1' => [
                    'class' => \app\modules\api\v1\Module::class,
                ],
            ],
        ],
    ],
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    if (class_exists('yii\debug\Module')) {
        $config['bootstrap'][] = 'debug';
        $config['modules']['debug'] = [
            'class' => 'yii\debug\Module',
            // uncomment the following to add your IP if you are not connecting from localhost.
            //'allowedIPs' => ['127.0.0.1', '::1'],
        ];
    }

    if (class_exists('yii\gii\Module')) {
        $config['bootstrap'][] = 'gii';
        $config['modules']['gii'] = [
            'class' => 'yii\gii\Module',
            // uncomment the following to add your IP if you are not connecting from localhost.
            //'allowedIPs' => ['127.0.0.1', '::1'],
        ];
    }
}

return $config;
