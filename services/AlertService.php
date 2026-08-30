<?php

declare(strict_types=1);

namespace app\services;

use app\models\Alert;
use app\models\AlertLog;
use app\models\Stock;
use app\models\WeeklyAnalysis;
use Yii;
use yii\log\Logger;

/**
 * Alert engine (task 10.3, 10.5, 10.6).
 *
 * Evaluasi setiap alert aktif terhadap weekly_analysis terbaru per simbol.
 * Cooldown anti-spam: alert tidak dikirim ulang sebelum alertCooldownHours
 * sejak last_triggered_at (params.php).
 *
 * Channel (10.5/10.6):
 *  - in-app: selalu — baris alert_log channel 'app' (list + read di /alert/notifications)
 *  - email: dikirim via Yii mailer ke email user (dev: useFileTransport → runtime/mail)
 * Telegram (10.7) P2: menyusul — cukup tambah channel baru di sini.
 */
final class AlertService
{
    /**
     * @param int|null $userId batasi ke user tertentu (null = semua user)
     * @return array{evaluated: int, triggered: int, logs: int}
     */
    public function evaluate(?int $userId = null): array
    {
        $query = Alert::find()
            ->where(['active' => true])
            ->with(['user', 'stock']);
        if ($userId !== null) {
            $query->andWhere(['user_id' => $userId]);
        }
        $alerts = $query->all();

        $cooldown = (int) (Yii::$app->params['alertCooldownHours'] ?? 24) * 3600;
        $now = time();
        $evaluated = 0;
        $triggered = 0;
        $logCount = 0;

        foreach ($alerts as $alert) {
            $evaluated++;

            // cooldown anti-spam
            if ($alert->last_triggered_at !== null && ($now - (int) $alert->last_triggered_at) < $cooldown) {
                continue;
            }

            foreach ($this->latestWeeklyRows($alert) as $weekly) {
                $value = $weekly->{$alert->condition_type};
                if ($value === null || !$alert->matches((float) $value)) {
                    continue;
                }

                $message = $this->buildMessage($alert, $weekly, (float) $value);
                $logCount += $this->notify($alert, $weekly, $message);
                $triggered++;
                $alert->last_triggered_at = $now;
            }

            if ($alert->isAttributeChanged('last_triggered_at')) {
                $alert->save(false, ['last_triggered_at']);
            }
        }

        Yii::info(sprintf('Alert evaluate: %d alerts evaluated, %d triggered, %d logs written.', $evaluated, $triggered, $logCount), 'app\services\AlertService');

        return ['evaluated' => $evaluated, 'triggered' => $triggered, 'logs' => $logCount];
    }

    /**
     * Weekly analysis terbaru untuk scope alert:
     * stock_id spesifik → satu simbol; null → semua simbol aktif.
     * @return WeeklyAnalysis[]
     */
    private function latestWeeklyRows(Alert $alert): array
    {
        if ($alert->stock_id !== null) {
            return $this->latestForStock($alert->stock_id);
        }

        $rows = [];
        $stockIds = Stock::find()->select('id')->where(['active' => true])->column();
        foreach ($stockIds as $stockId) {
            foreach ($this->latestForStock((int) $stockId) as $weekly) {
                $rows[] = $weekly;
            }
        }

        return $rows;
    }

    /** Weekly analysis minggu terakhir milik satu simbol (0/1 baris). */
    private function latestForStock(int $stockId): array
    {
        $weekly = WeeklyAnalysis::find()
            ->where(['stock_id' => $stockId])
            ->orderBy(['week_start' => SORT_DESC])
            ->one();

        return $weekly === null ? [] : [$weekly];
    }

    private function buildMessage(Alert $alert, WeeklyAnalysis $weekly, float $value): string
    {
        $symbol = $weekly->stock->symbol ?? (string) $weekly->stock_id;
        $condition = Alert::CONDITIONS[$alert->condition_type] ?? $alert->condition_type;

        return sprintf(
            '%s: %s %s %s (nilai: %s, score: %d, sinyal: %s, minggu %s)',
            $symbol,
            $condition,
            $alert->operator,
            $this->formatThreshold($alert),
            $this->formatValue($alert->condition_type, $value),
            $weekly->score,
            $weekly->signal,
            $weekly->week_start,
        );
    }

    private function formatThreshold(Alert $alert): string
    {
        return $alert->condition_type === Alert::CONDITION_BUY_RATIO
            ? (string) round((float) $alert->threshold, 2)
            : (string) round((float) $alert->threshold, 4);
    }

    private function formatValue(string $conditionType, float $value): string
    {
        return $conditionType === Alert::CONDITION_BUY_RATIO
            ? Yii::$app->formatter->asRatioPercent($value)
            : (string) round($value, 2);
    }

    /**
     * Kirim via channel & catat ke alert_log.
     * @return int jumlah baris log yang ditulis
     */
    private function notify(Alert $alert, WeeklyAnalysis $weekly, string $message): int
    {
        $count = 0;

        // 10.5 — in-app notification
        $log = new AlertLog();
        $log->alert_id = $alert->id;
        $log->stock_id = (int) $weekly->stock_id;
        $log->message = $message;
        $log->channel = AlertLog::CHANNEL_APP;
        $log->read_at = null;
        if ($log->save()) {
            $count++;
        } else {
            Yii::getLogger()->log('Gagal simpan alert_log (app): ' . json_encode($log->errors), Logger::LEVEL_ERROR);
        }

        // 10.6 — email channel
        $email = $alert->user->email ?? null;
        if (!empty($email)) {
            try {
                $sent = Yii::$app->mailer
                    ->compose()
                    ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
                    ->setTo($email)
                    ->setSubject('[Alert] ' . $message)
                    ->setTextBody($message . "\n\n— Stock Volume Analyzer")
                    ->send();
                if ($sent) {
                    $log = new AlertLog();
                    $log->alert_id = $alert->id;
                    $log->stock_id = (int) $weekly->stock_id;
                    $log->message = $message;
                    $log->channel = AlertLog::CHANNEL_EMAIL;
                    $log->read_at = null;
                    if ($log->save()) {
                        $count++;
                    }
                }
            } catch (\Throwable $e) {
                Yii::getLogger()->log('Email alert gagal: ' . $e->getMessage(), Logger::LEVEL_ERROR);
            }
        }

        return $count;
    }
}
