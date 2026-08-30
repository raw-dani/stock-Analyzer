<?php

declare(strict_types=1);

namespace app\models\form;

use app\models\Alert;
use app\models\Stock;
use yii\base\Model;

/**
 * Form CRUD alert (task 10.1).
 * symbol kosong = berlaku untuk semua simbol.
 */
final class AlertForm extends Model
{
    public ?string $symbol = null;
    public string $condition_type = Alert::CONDITION_SCORE;
    public string $operator = '>=';
    public ?float $threshold = null;
    public bool $active = true;

    public function rules(): array
    {
        return [
            [['condition_type', 'operator', 'threshold'], 'required'],
            [['condition_type'], 'in', 'range' => array_keys(Alert::CONDITIONS)],
            [['operator'], 'in', 'range' => array_keys(Alert::OPERATORS)],
            [['threshold'], 'number'],
            [['symbol'], 'string', 'max' => 16],
            [['symbol'], 'validateSymbol'],
            [['active'], 'boolean'],
        ];
    }

    public function validateSymbol(string $attribute): void
    {
        if ($this->symbol === null || $this->symbol === '') {
            return; // null = semua simbol
        }
        $exists = Stock::find()
            ->where(['symbol' => strtoupper($this->symbol), 'active' => true])
            ->exists();
        if (!$exists) {
            $this->addError($attribute, "Symbol {$this->symbol} tidak ditemukan.");
        }
    }
}
