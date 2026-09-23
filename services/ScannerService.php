<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\form\ScanFilterForm;
use app\models\WeeklyAnalysis;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * Stock scanner (task 6.2, 6.4) — query dinamis weekly_analysis + stock.
 * Smart ranking: sort by score DESC; filter tambahan market cap, sector, price range.
 */
final class ScannerService
{
    public function search(ScanFilterForm $form): ActiveDataProvider
    {
        // Guard: bila query string berisi nilai di luar rules (mis. market cap
        // raksasa), jangan jalankan query dengan nilai mentah — validasi dulu
        // agar filter invalid tidak diam-diam diabaikan andFilterWhere.
        if (!$form->validate()) {
            return new ActiveDataProvider([
                'query' => WeeklyAnalysis::find()->where('0=1'),
                'pagination' => ['pageSize' => $form->limit ?? 50],
            ]);
        }
        if ($form->mode === 'daily') {
            return $this->dailySearch($form);
        }
        return $this->weeklySearch($form);
    }

    public function weeklySearch(ScanFilterForm $form): ActiveDataProvider
    {
        $query = WeeklyAnalysis::find()
            ->joinWith(['stock'])
            ->andWhere(['{{%stock}}.active' => true]);

        if (!empty($form->symbol)) {
            $query->andFilterWhere(['like', '{{%stock}}.symbol', $form->symbol]);
        }
        $query->andFilterWhere(['{{%stock}}.exchange' => $form->exchange]);
        $query->andFilterWhere(['{{%weekly_analysis}}.signal' => $form->signal]);
        $query->andFilterWhere(['{{%stock}}.sector' => $form->sector]);
        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.buy_ratio', $form->minBuyRatio]);
        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.volume_growth', $form->minVolumeGrowth]);
        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.score', $form->minScore]);
        $query->andFilterWhere(['>=', '{{%stock}}.market_cap', $form->minMarketCap]);
        $query->andFilterWhere(['<=', '{{%stock}}.market_cap', $form->maxMarketCap]);
        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.close_price', $form->minPrice]);
        $query->andFilterWhere(['<=', '{{%weekly_analysis}}.close_price', $form->maxPrice]);

        if ($form->minVolume !== null) {
            $query->andWhere(['>=', new Expression('{{%weekly_analysis}}.buy_volume + {{%weekly_analysis}}.sell_volume'), $form->minVolume]);
        }

        // Today's results at top (most recent week first), then by score
        $query->orderBy([
            '{{%weekly_analysis}}.week_start' => SORT_DESC,
            '{{%weekly_analysis}}.score' => SORT_DESC,
        ]);

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => $form->limit ?? 50],
            'sort' => [
                'defaultOrder' => [
                    'week_start' => SORT_DESC,
                    'score' => SORT_DESC,
                ],
                'attributes' => [
                    'week_start' => [
                        'asc' => ['{{%weekly_analysis}}.week_start' => SORT_ASC],
                        'desc' => ['{{%weekly_analysis}}.week_start' => SORT_DESC],
                        'label' => 'Date',
                        'default' => SORT_DESC,
                    ],
                    'symbol' => [
                        'asc' => ['{{%stock}}.symbol' => SORT_ASC],
                        'desc' => ['{{%stock}}.symbol' => SORT_DESC],
                        'label' => 'Symbol',
                        'default' => SORT_ASC,
                    ],
                    'buy_ratio' => ['label' => 'Buy Ratio', 'default' => SORT_DESC],
                    'volume_growth' => ['label' => 'Vol Growth', 'default' => SORT_DESC],
                    'rvol' => ['label' => 'RVOL', 'default' => SORT_DESC],
                    'score' => ['label' => 'Score', 'default' => SORT_DESC],
                    'signal' => ['label' => 'Signal', 'default' => SORT_DESC],
                ],
            ],
        ]);
    }

    /**
     * Daily view — query daily_price untuk 7 hari terakhir.
     */
    public function dailySearch(ScanFilterForm $form): ActiveDataProvider
    {
        $query = DailyPrice::find()
            ->joinWith(['stock'])
            ->andWhere(['{{%stock}}.active' => true])
            ->andWhere(['>=', '{{%daily_price}}.date', date('Y-m-d', strtotime('-7 days'))])
            ->orderBy([
                '{{%daily_price}}.date' => SORT_DESC,
                '{{%stock}}.symbol' => SORT_ASC,
            ]);

        if (!empty($form->symbol)) {
            $query->andFilterWhere(['like', '{{%stock}}.symbol', $form->symbol]);
        }
        $query->andFilterWhere(['{{%stock}}.exchange' => $form->exchange]);
        $query->andFilterWhere(['{{%stock}}.sector' => $form->sector]);
        $query->andFilterWhere(['>=', '{{%daily_price}}.close', $form->minPrice]);
        $query->andFilterWhere(['<=', '{{%daily_price}}.close', $form->maxPrice]);

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => $form->limit ?? 50],
            'sort' => [
                'defaultOrder' => [
                    'date' => SORT_DESC,
                    'symbol' => SORT_ASC,
                ],
                'attributes' => [
                    'date' => [
                        'asc' => ['{{%daily_price}}.date' => SORT_ASC],
                        'desc' => ['{{%daily_price}}.date' => SORT_DESC],
                        'label' => 'Date',
                        'default' => SORT_DESC,
                    ],
                    'symbol' => [
                        'asc' => ['{{%stock}}.symbol' => SORT_ASC],
                        'desc' => ['{{%stock}}.symbol' => SORT_DESC],
                        'label' => 'Symbol',
                        'default' => SORT_ASC,
                    ],
                    'close' => [
                        'asc' => ['{{%daily_price}}.close' => SORT_ASC],
                        'desc' => ['{{%daily_price}}.close' => SORT_DESC],
                        'label' => 'Price',
                        'default' => SORT_DESC,
                    ],
                    'volume' => [
                        'asc' => ['{{%daily_price}}.volume' => SORT_ASC],
                        'desc' => ['{{%daily_price}}.volume' => SORT_DESC],
                        'label' => 'Volume',
                        'default' => SORT_DESC,
                    ],
                    'buy_volume' => [
                        'asc' => ['{{%daily_price}}.buy_volume' => SORT_ASC],
                        'desc' => ['{{%daily_price}}.buy_volume' => SORT_DESC],
                        'label' => 'Buy Vol',
                        'default' => SORT_DESC,
                    ],
                    'sell_volume' => [
                        'asc' => ['{{%daily_price}}.sell_volume' => SORT_ASC],
                        'desc' => ['{{%daily_price}}.sell_volume' => SORT_DESC],
                        'label' => 'Sell Vol',
                        'default' => SORT_DESC,
                    ],
                ],
            ],
        ]);
    }
}
