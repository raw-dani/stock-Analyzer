<?php

declare(strict_types=1);

namespace app\jobs;

use app\services\AlertService;
use yii\base\BaseObject;

/**
 * Queue job evaluasi alert (task 10.4).
 * Dipush oleh commands/AlertController::actionDispatch (cron).
 * Driver sync → dieksekusi inline; redis/db → worker `php yii queue/listen`.
 */
final class EvaluateAlertsJob extends BaseObject implements \yii\queue\JobInterface
{
    public ?int $userId = null;

    public function execute($queue): void
    {
        Yii::createObject(AlertService::class)->evaluate($this->userId);
    }
}
