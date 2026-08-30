<?php

declare(strict_types=1);

namespace app\models\form;

use app\models\WeeklyAnalysis;
use yii\base\Model;

/**
 * Filter histori sinyal (task 9.1) — tanggal/simbol/tipe.
 */
final class SignalFilterForm extends Model
{
    public ?string $symbol = null;
    public ?string $signal = null;
    public ?string $dateFrom = null; // Y-m-d
    public ?string $dateTo = null;   // Y-m-d

    public function formName(): string
    {
        return ''; // query string flat: ?symbol=NVDA&signal=BUY&dateFrom=...
    }

    public function rules(): array
    {
        return [
            [['symbol'], 'string', 'max' => 16],
            [['signal'], 'in', 'range' => array_keys(WeeklyAnalysis::SIGNALS)],
            [['dateFrom', 'dateTo'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }
}
