<?php

declare(strict_types=1);

namespace app\jobs;

use app\services\MarketDataService;
use yii\base\BaseObject;

/**
 * Queue job untuk sync satu simbol (task 2.5).
 * Dipush oleh DataController::actionFetch — dengan driver sync langsung
 * dieksekusi, dengan driver redis/db diproses worker `php yii queue/listen`.
 */
final class FetchSymbolJob extends BaseObject implements \yii\queue\JobInterface
{
    public string $symbol;
    public ?string $fromDate = null;
    public ?string $toDate = null;

    public function execute($queue): void
    {
        $provider = Yii::$app->get('marketData')->get();
        $service = new MarketDataService($provider);
        $service->syncSymbol($this->symbol, $this->fromDate, $this->toDate);
    }
}
