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
        Yii::info('Mencoba koneksi ke database...', 'app\services\scanner');
        try {
            Yii::$app->getDb()->createCommand('SELECT 1')->queryScalar();
            Yii::info('Berhasil terhubung ke database.', 'app\services\scanner');
        } catch (\Throwable $e) {
            $this->stderr('Gagal koneksi DB: ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $stocks = \app\models\Stock::find()->where(['active' => true])->all();
        $stockCount = count($stocks);
        $engine = new SignalEngineService();
        $total = 0;
        $signals = ['STRONG_BUY' => 0, 'BUY' => 0, 'WATCH' => 0, 'WEAK' => 0, 'SELL' => 0];

        Yii::info("Scan dimulai: {$stockCount} saham.", 'app\services\scanner');
        $this->stdout("Scanning {$stockCount} saham...\n", Console::FG_CYAN);

        $i = 0;
        foreach ($stocks as $stock) {
            $i++;
            $this->stdout("  [{$i}/{$stockCount}] {$stock->symbol}...", Console::FG_YELLOW);
            Yii::info("Proses scan saham: {$stock->symbol}", 'app\services\scanner');

            $count = $engine->scoreStock($stock);
            $total += $count;

            $latestSignal = WeeklyAnalysis::find()
                ->select('signal')
                ->where(['stock_id' => $stock->id])
                ->orderBy(['week_start' => SORT_DESC])
                ->scalar();
            if (isset($signals[$latestSignal])) {
                $signals[$latestSignal]++;
            }

            $this->stdout(" OK ({$count} minggu, sinyal: {$latestSignal})\n", Console::FG_GREEN);
            Yii::info("Sukses scan {$stock->symbol}: {$count} minggu discoring (sinyal: {$latestSignal})", 'app\services\scanner');
        }

        $distText = json_encode($signals);
        Yii::info("Proses scan selesai: {$total} total minggu discoring. {$distText}", 'app\services\scanner');

        $this->stdout("\nScanned {$total} weekly rows.\n\n", Console::FG_GREEN);

        $summary = WeeklyAnalysis::find()
            ->select(['signal', 'COUNT(*) AS cnt'])
            ->groupBy('signal')
            ->asArray()
            ->all();

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
