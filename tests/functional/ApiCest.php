<?php

/**
 * Functional test: REST API v1 (task 13.1–13.3).
 * Public endpoints JSON; user-scoped pakai Bearer token admin (100-token dari seed).
 * Catatan: suite functional tidak punya modul REST — konten JSON dicek via see().
 */
class ApiCest
{
    public function stocksListReturnsJson(\FunctionalTester $I)
    {
        $I->amOnRoute('api/v1/stocks');
        $I->seeResponseCodeIs(200);
        $I->see('NVDA');
    }

    public function stockDetailReturnsWeeklyAndBars(\FunctionalTester $I)
    {
        $I->amOnRoute('api/v1/stocks/view', ['symbol' => 'NVDA']);
        $I->seeResponseCodeIs(200);
        $I->see('latestWeekly');
        $I->see('score');
    }

    public function unknownStockReturnsJson404(\FunctionalTester $I)
    {
        $I->amOnRoute('api/v1/stocks/view', ['symbol' => 'ZZZZZZ']);
        $I->seeResponseCodeIs(404);
        $I->see('not found');
    }

    public function scannerEndpointFiltersSignal(\FunctionalTester $I)
    {
        $I->amOnRoute('api/v1/scanner', ['signal' => 'STRONG_BUY', 'limit' => 5]);
        $I->seeResponseCodeIs(200);
        $I->see('totalCount');
        $I->see('STRONG_BUY');
    }

    public function signalsEndpoint(\FunctionalTester $I)
    {
        $I->amOnRoute('api/v1/signals', ['symbol' => 'NVDA']);
        $I->seeResponseCodeIs(200);
        $I->see('reasons');
    }

    public function watchlistRequiresBearer(\FunctionalTester $I)
    {
        $I->amOnRoute('api/v1/watchlists');
        $I->seeResponseCodeIs(401);
    }

    public function watchlistWithBearerToken(\FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer 100-token');
        $I->amOnRoute('api/v1/watchlists');
        $I->seeResponseCodeIs(200);
        $I->see('items');
    }

    public function alertsEndpointWithBearerToken(\FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer 100-token');
        $I->amOnRoute('api/v1/alerts');
        $I->seeResponseCodeIs(200);
        $I->see('items');
    }
}
