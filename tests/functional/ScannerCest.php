<?php

/**
 * Functional test: scanner filter (task 6.5).
 *
 * Memakai SQLite dev DB (@app/data/stocks.db) yang sudah berisi
 * fixture NVDA/AAPL/XOM + daily_price + weekly_analysis + signal.
 * Test bersifat read-only (hanya GET).
 */
class ScannerCest
{
    public function scannerPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('scanner/index');
        $I->see('Stock Scanner', 'h1');
        $I->seeElement('table.table');
    }

    public function filterBySignal(\FunctionalTester $I)
    {
        $I->amOnRoute('scanner/index', ['signal' => 'STRONG_BUY']);
        $I->see('Stock Scanner', 'h1');
        $I->seeElement('table.table');
        $I->see('STRONG BUY');
    }

    public function filterByMinScore(\FunctionalTester $I)
    {
        $I->amOnRoute('scanner/index', ['minScore' => 80]);
        $I->see('Stock Scanner', 'h1');
        $I->seeElement('table.table');
        // cari simbol di hasil (setidaknya ada 1 baris)
        $I->seeElement('tbody tr');
    }

    public function filterByExchange(\FunctionalTester $I)
    {
        $I->amOnRoute('scanner/index', ['exchange' => 'NASDAQ']);
        $I->see('Stock Scanner', 'h1');
        $I->seeElement('table.table');
        $I->seeElement('tbody tr');
    }

    public function filterByMinBuyRatio(\FunctionalTester $I)
    {
        $I->amOnRoute('scanner/index', ['minBuyRatio' => 0.7]);
        $I->see('Stock Scanner', 'h1');
        $I->seeElement('table.table');
    }

    public function filterFormSubmittedWithMinScore(\FunctionalTester $I)
    {
        $I->amOnRoute('scanner/index');
        // formName='' → field name flat (tanpa prefix model)
        $I->submitForm('form', [
            'minScore' => 70,
        ]);
        $I->see('Stock Scanner', 'h1');
        $I->seeElement('table.table');
    }
}
