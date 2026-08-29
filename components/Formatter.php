<?php

declare(strict_types=1);

namespace app\components;

use Yii;

/**
 * Formatter custom untuk US Stock Volume Analyzer.
 * Menambahkan format volume saham (68.2M, 1.4B) dan persentase 1 desimal (76.1%).
 */
class Formatter extends \yii\i18n\Formatter
{
    /**
     * Format volume angka besar menjadi ringkas.
     * 68_200_000 -> "68.2M", 1_400_000_000 -> "1.40B", 850_000 -> "850K"
     */
    public function asVolume($value): string
    {
        if ($value === null || $value === '') {
            return $this->nullDisplay;
        }

        $value = (float) $value;
        $abs = abs($value);

        if ($abs >= 1_000_000_000) {
            return rtrim(rtrim(sprintf('%.2fB', $value / 1_000_000_000), '0'), '.') . 'B';
        }
        if ($abs >= 1_000_000) {
            return rtrim(rtrim(sprintf('%.1fM', $value / 1_000_000), '0'), '.') . 'M';
        }
        if ($abs >= 1_000) {
            return rtrim(rtrim(sprintf('%.0fK', $value / 1_000), '0'), '.') . 'K';
        }

        return (string) $value;
    }

    /**
     * Persentase dengan 1 desimal dari nilai rasio.
     * 0.761 -> "76.1%"
     */
    public function asRatioPercent($value): string
    {
        if ($value === null || $value === '') {
            return $this->nullDisplay;
        }

        return sprintf('%.1f%%', (float) $value * 100);
    }

    /**
     * Persentase pertumbuhan bertanda.
     * 0.42 -> "+42%", -0.03 -> "-3%"
     */
    public function asGrowthPercent($value): string
    {
        if ($value === null || $value === '') {
            return $this->nullDisplay;
        }

        $pct = (float) $value * 100;
        return ($pct >= 0 ? '+' : '') . sprintf('%.0f%%', $pct);
    }

    /**
     * Harga saham dengan tanda dolar: 182.4 -> "$182.40"
     */
    public function asStockPrice($value): string
    {
        if ($value === null || $value === '') {
            return $this->nullDisplay;
        }

        return '$' . Yii::$app->getFormatter()->asDecimal((float) $value, 2);
    }
}
