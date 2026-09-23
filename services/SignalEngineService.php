<?php

declare(strict_types=1);

namespace app\services;

use app\models\WeeklyAnalysis;
use app\services\dto\SignalResult;
use app\services\dto\WeeklyIndicators;
use Yii;
use yii\base\Component;

/**
 * Signal Engine (Modul 4) — scoring 0-100 sesuai desain §9/§11.
 * Threshold bisa dioverride via params 'signalThresholds'.
 */
final class SignalEngineService extends Component
{
    private array $t;

    public function init(): void
    {
        parent::init();
        $this->t = Yii::$app->params['signalThresholds'] ?? [
            'buyRatio' => [['min' => 0.70, 'points' => 30], ['min' => 0.60, 'points' => 20], ['min' => 0.55, 'points' => 10]],
            'volumeGrowth' => [['min' => 0.50, 'points' => 25], ['min' => 0.25, 'points' => 15], ['min' => 0.10, 'points' => 10]],
            'rvol' => [['min' => 2.0, 'points' => 20], ['min' => 1.5, 'points' => 15], ['min' => 1.2, 'points' => 10]],
            'aboveMa20' => 15,
            'aboveMa50' => 10,
        ];
    }

    public function evaluate(WeeklyIndicators $i): SignalResult
    {
        $score = 0;
        $reasons = [];

        // Buy Pressure
        foreach ($this->t['buyRatio'] as $rule) {
            if ($i->buyRatio >= $rule['min']) {
                $score += $rule['points'];
                $reasons[] = sprintf('Buy ratio %.1f%% (>= %.0f%%)', $i->buyRatio * 100, $rule['min'] * 100);
                break;
            }
        }

        // Volume Growth
        if ($i->volumeGrowth !== null) {
            foreach ($this->t['volumeGrowth'] as $rule) {
                if ($i->volumeGrowth >= $rule['min']) {
                    $score += $rule['points'];
                    $reasons[] = sprintf('Volume growth +%.0f%% (>= %.0f%%)', $i->volumeGrowth * 100, $rule['min'] * 100);
                    break;
                }
            }
        }

        // Relative Volume
        if ($i->rvol !== null) {
            foreach ($this->t['rvol'] as $rule) {
                if ($i->rvol >= $rule['min']) {
                    $score += $rule['points'];
                    $reasons[] = sprintf('RVOL %.1fx (>= %.1fx)', $i->rvol, $rule['min']);
                    break;
                }
            }
        }

        // Price Trend
        if ($i->ma20 !== null && $i->closePrice > $i->ma20) {
            $score += $this->t['aboveMa20'];
            $reasons[] = 'Price > MA20';
        }
        if ($i->ma50 !== null && $i->closePrice > $i->ma50) {
            $score += $this->t['aboveMa50'];
            $reasons[] = 'Price > MA50';
        }

        $score = min(100, $score);

        return new SignalResult(
            score: $score,
            signal: self::classify($score),
            reasons: $reasons,
        );
    }

    /**
     * Scoring + persistensi untuk semua weekly_analysis milik satu simbol (task 4.3).
     * - Update score/signal di weekly_analysis
     * - Simpan histori ke tabel signal (hanya bila berubah)
     *
     * @return int jumlah minggu yang discoring
     */
    public function scoreStock(\app\models\Stock $stock): int
    {
        $rows = WeeklyAnalysis::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy('week_start')
            ->all();

        $count = 0;
        foreach ($rows as $row) {
            $ind = new WeeklyIndicators(
                stockId: $stock->id,
                weekStart: $row->week_start,
                buyVolume: (int) $row->buy_volume,
                sellVolume: (int) $row->sell_volume,
                buyRatio: (float) $row->buy_ratio,
                sellRatio: (float) $row->sell_ratio,
                buySellRatio: (float) $row->buy_sell_ratio,
                volumeGrowth: $row->volume_growth === null ? null : (float) $row->volume_growth,
                rvol: $row->rvol === null ? null : (float) $row->rvol,
                ma20: $row->ma20 === null ? null : (float) $row->ma20,
                ma50: $row->ma50 === null ? null : (float) $row->ma50,
                closePrice: (float) $row->close_price,
            );

            $result = $this->evaluate($ind);
            $changed = ((int) $row->score !== $result->score) || ($row->signal !== $result->signal);

            $row->score = $result->score;
            $row->signal = $result->signal;
            $row->save(false);
            $count++;

            if ($changed) {
                // histori sinyal — satu baris per (stock, week) di tabel signal
                $existing = \app\models\Signal::find()
                    ->where(['stock_id' => $stock->id, 'date' => $row->week_start])
                    ->one();
                if ($existing === null) {
                    $existing = new \app\models\Signal();
                    $existing->stock_id = $stock->id;
                    $existing->weekly_id = $row->id;
                    $existing->date = $row->week_start;
                    $existing->created_at = time();
                }
                $existing->price = $row->close_price;
                $existing->score = $result->score;
                $existing->signal = $result->signal;
                $existing->buy_ratio = $ind->buyRatio;
                $existing->rvol = (float) ($ind->rvol ?? 0);
                $existing->volume_growth = $ind->volumeGrowth;
                $existing->reason = json_encode($result->reasons, JSON_UNESCAPED_SLASHES);
                $existing->updated_at = time();
                $existing->save(false);
            }
        }

        return $count;
    }

    /**
     * Scoring semua simbol aktif (task 4.4).
     */
    public function scoreAll(): int
    {
        $stocks = \app\models\Stock::find()->where(['active' => true]);
        return $this->scoreByQuery($stocks);
    }

    /**
     * Scoring simbol sesuai query filter (mis. by exchange).
     */
    public function scoreByQuery(\yii\db\Query $query): int
    {
        $total = 0;
        foreach ($query->all() as $stock) {
            $total += $this->scoreStock($stock);
        }
        return $total;
    }

    /**
     * Klasifikasi skor -> sinyal (task 4.2).
     */
    public static function classify(int $score): string
    {
        return match (true) {
            $score >= 80 => WeeklyAnalysis::SIGNAL_STRONG_BUY,
            $score >= 65 => WeeklyAnalysis::SIGNAL_BUY,
            $score >= 50 => WeeklyAnalysis::SIGNAL_WATCH,
            $score >= 35 => WeeklyAnalysis::SIGNAL_WEAK,
            default => WeeklyAnalysis::SIGNAL_SELL,
        };
    }
}
