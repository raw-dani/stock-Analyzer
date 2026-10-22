<?php

require __DIR__ . '/../bootstrap-env.php';

$envDebug = getenv('YII_DEBUG');
$envEnv = getenv('YII_ENV');

defined('YII_DEBUG') or define('YII_DEBUG', $envDebug !== false ? filter_var($envDebug, FILTER_VALIDATE_BOOLEAN) : false);
defined('YII_ENV') or define('YII_ENV', $envEnv !== false && $envEnv !== '' ? $envEnv : 'prod');

// Mitigasi Keamanan CVE-2026-109618 / CVE-2026-109619 (PHP2Shell: Upload Progress UAF)
// Nonaktifkan fitur upload progress PHP pada level runtime
@ini_set('session.upload_progress.enabled', '0');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
