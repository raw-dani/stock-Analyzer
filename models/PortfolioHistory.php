<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model Riwayat Eksekusi Averaging Down & Transaksi Portofolio.
 *
 * @property int $id
 * @property int $position_id
 * @property int $user_id
 * @property string $action_type
 * @property string|null $scenario_name
 * @property float|null $old_price
 * @property float|null $new_price
 * @property float|null $old_shares
 * @property float|null $added_shares
 * @property float|null $new_shares
 * @property float|null $added_price
 * @property float|null $added_cost
 * @property string|null $notes
 * @property int $created_at
 *
 * @property-read PortfolioPosition $position
 * @property-read User $user
 */
class PortfolioHistory extends ActiveRecord
{
    public const ACTION_AVERAGE_DOWN = 'AVERAGE_DOWN';
    public const ACTION_BUY_INITIAL = 'BUY_INITIAL';
    public const ACTION_EDIT = 'EDIT';
    public const ACTION_SELL = 'SELL';
    public const ACTION_CUT_LOSS = 'CUT_LOSS';

    public static function tableName(): string
    {
        return '{{%portfolio_history}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['position_id', 'user_id', 'action_type'], 'required'],
            [['position_id', 'user_id', 'created_at'], 'integer'],
            [['old_price', 'new_price', 'old_shares', 'added_shares', 'new_shares', 'added_price', 'added_cost'], 'number'],
            [['notes'], 'string'],
            [['action_type'], 'string', 'max' => 32],
            [['scenario_name'], 'string', 'max' => 120],
        ];
    }

    public function getPosition(): ActiveQuery
    {
        return $this->hasOne(PortfolioPosition::class, ['id' => 'position_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Dapatkan simbol saham terkait.
     * Mengutamakan relasi position->stock, fallback ke scenario_name atau ekstraksi dari notes.
     */
    public function getStockSymbol(): string
    {
        if ($this->position && $this->position->stock) {
            return $this->position->stock->symbol;
        }

        // Cek jika simbol tersimpan di scenario_name dengan format "Aksi [SYMBOL]" atau "Aksi - SYMBOL"
        if (!empty($this->scenario_name) && preg_match('/\[([A-Za-z0-9\.\-\^]+)\]/', $this->scenario_name, $m)) {
            return strtoupper($m[1]);
        }

        // Cek jika ada pola saham di notes, misal "[NVDA]" atau "posisi NVDA"
        if (!empty($this->notes) && preg_match('/(?:posisi|saham|emiten)\s+([A-Za-z0-9\.\-\^]+)/i', $this->notes, $m)) {
            return strtoupper($m[1]);
        }

        if (!empty($this->notes) && preg_match('/\[([A-Za-z0-9\.\-\^]{1,10})\]/', $this->notes, $m)) {
            return strtoupper($m[1]);
        }

        return '—';
    }

    /**
     * Hitung Realisasi Profit/Loss dalam mata uang ($).
     */
    public function getRealizedPlAmount(): float
    {
        if (!in_array($this->action_type, [self::ACTION_SELL, self::ACTION_CUT_LOSS], true)) {
            return 0.0;
        }

        $sellPrice = (float) ($this->added_price ?: $this->new_price);
        $buyPrice = (float) $this->old_price;
        $shares = abs((float) $this->added_shares);

        if ($shares <= 0 && (float) $this->old_shares > 0) {
            $shares = (float) $this->old_shares - (float) $this->new_shares;
        }

        return round(($sellPrice - $buyPrice) * $shares, 2);
    }

    /**
     * Hitung Realisasi Profit/Loss dalam persentase (%).
     */
    public function getRealizedPlPct(): float
    {
        $buyPrice = (float) $this->old_price;
        if ($buyPrice <= 0) {
            return 0.0;
        }

        $sellPrice = (float) ($this->added_price ?: $this->new_price);
        return round((($sellPrice - $buyPrice) / $buyPrice) * 100, 2);
    }
}

