<?php

declare(strict_types=1);

namespace app\services\market;

use Yii;
use yii\base\InvalidConfigException;

/**
 * Component 'marketData' — memilih DataProvider aktif berdasarkan config/params.php
 * (params: 'marketDataProvider' => alpha_vantage | polygon | csv).
 */
class DataProviderFactory extends \yii\base\Component
{
    public function get(string $provider = null): DataProviderInterface
    {
        $provider ??= (string) (Yii::$app->params['marketDataProvider'] ?? 'csv');
        $config = Yii::$app->params['marketDataProviderConfig'][$provider] ?? null;

        if ($config === null) {
            throw new InvalidConfigException("Unknown market data provider: {$provider}");
        }

        $class = match ($provider) {
            'alpha_vantage' => AlphaVantageProvider::class,
            'polygon' => PolygonProvider::class,
            'yahoo_finance' => YahooFinanceProvider::class,
            'csv' => CsvImportProvider::class,
            default => throw new InvalidConfigException("No provider class mapped for: {$provider}"),
        };

        return Yii::createObject(array_merge(['class' => $class], $config));
    }
}
