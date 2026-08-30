<?php

declare(strict_types=1);

namespace app\commands;

use app\jobs\EvaluateAlertsJob;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * php yii alert/dispatch — evaluasi semua alert aktif via queue (task 10.4).
 * Jadwalkan via cron sesuai jam close pasar AS (desain §10 / task 15.2).
 */
final class AlertController extends Controller
{
    public function actionDispatch(): int
    {
        $this->stdout('Pushing EvaluateAlertsJob to queue...', Console::FG_CYAN);
        Yii::$app->queue->push(new EvaluateAlertsJob());
        $this->stdout(" done.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
