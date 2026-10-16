<?php

require __DIR__ . '/../bootstrap-env.php';

$envDebug = getenv('YII_DEBUG');
$envEnv = getenv('YII_ENV');

defined('YII_DEBUG') or define('YII_DEBUG', $envDebug !== false ? filter_var($envDebug, FILTER_VALIDATE_BOOLEAN) : false);
defined('YII_ENV') or define('YII_ENV', $envEnv !== false && $envEnv !== '' ? $envEnv : 'prod');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
