<?php

/**
 * Functional test: Sector Analysis & Heatmap (task 12.1–12.3).
 * Read-only, memakai dev DB (fixture NVDA/AAPL/XOM dgn sector).
 */
class SectorCest
{
    public function heatmapRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('sector/index');
        $I->see('Sector Heatmap', 'h1');
        // fixture memiliki >= 1 sektor dan kartu heat
        $I->seeElement('.card');
    }

    public function drillDownRenders(\FunctionalTester $I)
    {
        // ambil satu sektor dari DB dev (fixture: Technology/Energy)
        $sector = \app\models\Stock::find()
            ->select('sector')
            ->distinct()
            ->where(['not', ['sector' => null]])
            ->scalar();
        $I->assertNotEmpty($sector, 'Fixture harus punya minimal satu sektor');

        $I->amOnRoute('sector/view', ['sector' => $sector]);
        $I->see($sector, 'h1');
        $I->seeElement('table.table');
        $I->seeElement('tbody tr');
    }

    public function unknownSectorRendersErrorPage(\FunctionalTester $I)
    {
        // error handler Yii mengubah exception menjadi halaman error
        $I->amOnRoute('sector/view', ['sector' => 'NO_SUCH_SECTOR_XYZ']);
        $I->see('Not Found');
    }
}
