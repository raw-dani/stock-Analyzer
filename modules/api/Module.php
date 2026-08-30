<?php

declare(strict_types=1);

namespace app\modules\api;

use yii\base\Module as BaseModule;

/**
 * REST API root module. Nested: api/v1 (task 13.1).
 */
final class Module extends BaseModule
{
    public $id = 'api';
}
