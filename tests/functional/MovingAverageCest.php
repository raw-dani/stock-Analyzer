<?php

declare(strict_types=1);

/**
 * Functional test: Moving Average Strategy & Trade Levels scanner.
 */
class MovingAverageCest
{
    public function movingAveragePageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('moving-average/index');
        $I->see('Moving Average', 'h1');
        $I->seeElement('table#ma-grid-table');
    }

    public function filterByBuyStrategy(\FunctionalTester $I)
    {
        $I->amOnRoute('moving-average/index', ['strategy' => 'buy_all']);
        $I->see('Moving Average', 'h1');
        $I->seeElement('table#ma-grid-table');
    }

    public function filterByMaPair(\FunctionalTester $I)
    {
        $I->amOnRoute('moving-average/index', ['maPair' => '50_200']);
        $I->see('Moving Average', 'h1');
        $I->seeElement('table#ma-grid-table');
    }

    public function filterByMinRr(\FunctionalTester $I)
    {
        $I->amOnRoute('moving-average/index', ['minRr' => 1.5]);
        $I->see('Moving Average', 'h1');
        $I->seeElement('table#ma-grid-table');
    }

    public function detailPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('moving-average/detail', ['symbol' => 'NVDA']);
        $I->see('NVDA', 'h1');
        $I->see('Posisi Beli (Entry)');
        $I->see('Stop Loss');
        $I->see('Target Profit 1');
    }

    public function exportCsvWorks(\FunctionalTester $I)
    {
        $I->amOnRoute('moving-average/export');
        $I->seeResponseCodeIs(200);
        $I->see('Simbol');
    }

    public function filterByTimeframe2H(\FunctionalTester $I)
    {
        $I->amOnRoute('moving-average/index', ['timeframe' => 2]);
        $I->see('Moving Average', 'h1');
        $I->see('2 Jam (2H)');
        $I->seeElement('table#ma-grid-table');
    }

    public function detailPageTimeframe2H(\FunctionalTester $I)
    {
        $I->amOnRoute('moving-average/detail', ['symbol' => 'NVDA', 'timeframe' => 2]);
        $I->see('NVDA', 'h1');
        $I->see('2 Jam (2H)');
        $I->see('Posisi Beli (Entry)');
    }
}
