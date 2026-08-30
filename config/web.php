<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', \app\components\SettingsBootstrap::class],
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
        'queue' => [
            'class' => \yii\queue\sync\Queue::class,
            // Production: ganti ke redis driver (desain §15.3)
        ],
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
            'targets' => [
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
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
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
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
