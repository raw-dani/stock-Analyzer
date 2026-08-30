<?php

declare(strict_types=1);

namespace app\services;

use app\models\form\ScanFilterForm;
use app\models\WeeklyAnalysis;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * Stock scanner (task 6.2) — query dinamis weekly_analysis + stock.
 */
final class ScannerService
{
    public function search(ScanFilterForm $form): ActiveDataProvider
    {
        $query = WeeklyAnalysis::find()
            ->joinWith(['stock'])
            ->andWhere(['{{%stock}}.active' => true]);

        if (!empty($form->symbol)) {
            $query->andFilterWhere(['like', '{{%stock}}.symbol', $form->symbol]);
        }
        $query->andFilterWhere(['{{%stock}}.exchange' => $form->exchange]);
        $query->andFilterWhere(['{{%weekly_analysis}}.signal' => $form->signal]);
        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.buy_ratio', $form->minBuyRatio]);
        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.volume_growth', $form->minVolumeGrowth]);
        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.score', $form->minScore]);

        // hanya minggu terakhir per simbol? default: semua minggu; sort score desc
        $query->orderBy(['{{%weekly_analysis}}.score' => SORT_DESC, '{{%weekly_analysis}}.week_start' => SORT_DESC]);

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => $form->limit ?? 50],
            'sort' => false,
        ]);
    }
}
