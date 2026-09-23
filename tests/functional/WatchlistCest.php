<?php

/**
 * Functional test: watchlist CRUD + item add/remove (task 8.1–8.3).
 *
 * Memakai SQLite dev DB (@app/data/stocks.db) yang sudah berisi
 * fixture saham (AAPL, NVDA, XOM). Test ini akan membuat & menghapus
 * user + watchlist dummy secara eksplisit di _before/_after.
 */
class WatchlistCest
{
    private int $testUserId = 0;

    public function _before(\FunctionalTester $I)
    {
        $user = new \app\models\User();
        $user->username = 'testuser_' . uniqid();
        $user->email = 'test_' . uniqid() . '@example.com';
        $user->status = \app\models\User::STATUS_ACTIVE;
        $user->setPassword('Password123');
        $user->generateAuthKey();
        $user->save(false);
        $this->testUserId = (int) $user->id;

        $I->amOnRoute('site/login');
        $I->submitForm('#login-form', [
            'LoginForm[username]' => $user->username,
            'LoginForm[password]' => 'Password123',
        ]);
        $I->see('Logout', 'form');
        $I->amOnRoute('watchlist/index');
    }

    public function _after(\FunctionalTester $I)
    {
        if ($this->testUserId > 0) {
            \app\models\WatchlistItem::deleteAll(['watchlist_id' => \app\models\Watchlist::find()
                ->select('id')
                ->where(['user_id' => $this->testUserId])
                ->column()]);
            \app\models\Watchlist::deleteAll(['user_id' => $this->testUserId]);
            \app\models\User::deleteAll(['id' => $this->testUserId]);
        }
    }

    private function latestWatchlistId(\FunctionalTester $I): int
    {
        $wl = \app\models\Watchlist::find()
            ->where(['user_id' => $this->testUserId])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($wl === null) {
            $wl = new \app\models\Watchlist();
            $wl->user_id = $this->testUserId;
            $wl->name = 'Daftar Saham';
            $wl->description = 'Auto-created for testing';
            $wl->save(false);
        }

        $I->assertNotNull($wl, 'Expected at least one watchlist for this user');
        return (int) $wl->id;
    }

    public function guestCannotAccessWatchlist(\FunctionalTester $I)
    {
        \Yii::$app->user->logout();
        $I->amOnRoute('site/login');
        $I->see('Login', 'h1');

        $I->amOnRoute('watchlist/index');
        $I->see('Login', 'a');
    }

    public function registerPageRenders(\FunctionalTester $I)
    {
        \Yii::$app->user->logout();
        $I->amOnRoute('site/register');
        $I->see('Register', 'h1');
        $I->seeElement('#register-form');
    }

    public function registerNewUser(\FunctionalTester $I)
    {
        \Yii::$app->user->logout();
        $I->amOnRoute('site/register');
        $username = 'reguser_' . uniqid();
        $I->submitForm('#register-form', [
            'RegisterForm[username]' => $username,
            'RegisterForm[email]' => $username . '@example.com',
            'RegisterForm[password]' => 'Password123',
        ]);

        $I->see('Login', 'h1');
        $I->see('Registrasi berhasil', '.alert-success');

        \app\models\User::deleteAll(['username' => $username]);
    }

    public function watchlistIndexPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('watchlist/index');
        $I->see('Watchlist Saya', 'h1');
        $I->see('Buat Watchlist', 'a');
    }

    public function createWatchlist(\FunctionalTester $I)
    {
        $I->amOnRoute('watchlist/create');
        $I->see('Buat Watchlist', 'h1');
        $I->submitForm('#watchlist-form', [
            'Watchlist[name]' => 'Daftar Saham',
            'Watchlist[description]' => 'Saham yang sedang dipantau',
        ]);

        $I->see('Daftar Saham', 'h1');
        $I->see('Saham yang sedang dipantau', '.text-muted');
    }

    public function viewWatchlistWithAddForm(\FunctionalTester $I)
    {
        $id = $this->latestWatchlistId($I);
        $I->amOnRoute('watchlist/view', ['id' => $id]);
        $I->see('Daftar Saham');
        $I->seeElement('#symbol-input');
    }

    public function addAndRemoveItem(\FunctionalTester $I)
    {
        $id = $this->latestWatchlistId($I);
        $I->amOnRoute('watchlist/view', ['id' => $id]);
        $I->dontSee('AAPL');

        $I->submitForm('#add-item-form', [
            'symbol' => 'AAPL',
        ]);

        $I->see('AAPL');

        $item = \app\models\WatchlistItem::find()
            ->where(['watchlist_id' => $id])
            ->joinWith('stock')
            ->where(['stock.symbol' => 'AAPL'])
            ->one();
        $I->assertNotNull($item, 'Expected AAPL item in watchlist');

        $I->amOnRoute('watchlist/remove-item', ['id' => $item->id]);
        $I->dontSee('AAPL');
    }

    public function updateWatchlist(\FunctionalTester $I)
    {
        $id = $this->latestWatchlistId($I);
        $I->amOnRoute('watchlist/update', ['id' => $id]);
        $I->submitForm('#watchlist-form', [
            'Watchlist[name]' => 'Watchlist Update',
            'Watchlist[description]' => 'Diupdate',
        ]);

        $I->see('Watchlist Update', 'h1');
        $I->see('Diupdate', '.text-muted');
    }

    public function deleteWatchlist(\FunctionalTester $I)
    {
        $id = $this->latestWatchlistId($I);
        $I->amOnRoute('watchlist/view', ['id' => $id]);
        $I->see('Daftar Saham');

        $I->amOnRoute('watchlist/delete', ['id' => $id]);

        $I->see('Watchlist Saya', 'h1');
        $I->see('Belum ada watchlist');
    }
}
