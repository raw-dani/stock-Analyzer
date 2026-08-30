<?php

declare(strict_types=1);

namespace app\models\form;

use Yii;
use yii\base\Model;

/**
 * Filter stock scanner (task 6.1).
 */
final class ScanFilterForm extends Model
{
    public ?string $symbol = null;
    public ?string $exchange = null;
    public ?string $signal = null;
    public ?float $minBuyRatio = null;       // 0..1
    public ?float $minVolumeGrowth = null;   // 0..1
    public ?int $minScore = null;
    public ?int $limit = 50;

    public function formName(): string
    {
        return ''; // query string flat: ?exchange=NASDAQ&minScore=70
    }

    public function rules(): array
    {
        return [
            [['symbol', 'exchange'], 'string', 'max' => 16],
            [['signal'], 'in', 'range' => array_keys(\app\models\WeeklyAnalysis::SIGNALS)],
            [['minBuyRatio'], 'number', 'min' => 0, 'max' => 1],
            [['minVolumeGrowth'], 'number'],
            [['minScore', 'limit'], 'integer', 'min' => 1, 'max' => 500],
            [['limit'], 'default', 'value' => 50],
        ];
    }
}
