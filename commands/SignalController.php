<?php

declare(strict_types=1);

namespace app\commands;

use app\models\Signal;
use app\models\WeeklyAnalysis;
use app\services\SignalEngineService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * php yii signal/scan — jalankan signal engine untuk semua simbol (task 4.4).
 */
final class SignalController extends Controller
{
    public function actionScan(): int
    {
        $engine = new SignalEngineService();
        $total = $engine->scoreAll();

        $summary = WeeklyAnalysis::find()
            ->select(['signal', 'COUNT(*) AS cnt'])
            ->groupBy('signal')
            ->asArray()
            ->all();

        $this->stdout("Scored {$total} weekly rows.\n\n", Console::FG_GREEN);
        foreach ($summary as $row) {
            $this->stdout(sprintf("  %-12s : %d\n", $row['signal'], $row['cnt']));
        }

        $strong = Signal::find()->where(['signal' => 'STRONG_BUY'])->orderBy(['date' => SORT_DESC])->limit(10)->all();
        if ($strong !== []) {
            $this->stdout("\nRecent STRONG BUY:\n", Console::BOLD);
            foreach ($strong as $s) {
                $this->stdout(sprintf("  %s  %s  score=%d  reason=%s\n", $s->date, $s->stock->symbol, $s->score, implode('; ', $s->reasonList)));
            }
        }

        return ExitCode::OK;
    }
}
