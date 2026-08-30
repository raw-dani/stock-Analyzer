<?php

declare(strict_types=1);

namespace app\widgets;

use app\models\WeeklyAnalysis;
use yii\base\Widget;
use yii\helpers\Html;

/**
 * Badge sinyal berwarna (task 5.6), reusable.
 * Usage: <?= SignalBadge::widget(['signal' => $row->signal]) ?>
 */
final class SignalBadge extends Widget
{
    public ?string $signal = null;

    private const COLORS = [
        WeeklyAnalysis::SIGNAL_STRONG_BUY => 'success',
        WeeklyAnalysis::SIGNAL_BUY => 'success',
        WeeklyAnalysis::SIGNAL_WATCH => 'warning',
        WeeklyAnalysis::SIGNAL_WEAK => 'danger',
        WeeklyAnalysis::SIGNAL_SELL => 'dark',
    ];

    public function run(): string
    {
        if ($this->signal === null || $this->signal === '') {
            return '';
        }
        $label = WeeklyAnalysis::SIGNALS[$this->signal] ?? $this->signal;
        $color = self::COLORS[$this->signal] ?? 'secondary';

        return Html::tag('span', Html::encode($label), [
            'class' => "badge bg-{$color}",
            'style' => $this->signal === WeeklyAnalysis::SIGNAL_BUY ? 'background-color:#5cb85c !important' : '',
        ]);
    }
}
