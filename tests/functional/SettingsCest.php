<?php

/**
 * Functional test: Settings & Admin (Modul 14).
 * - RBAC: halaman settings hanya untuk admin (14.2)
 * - Form pengaturan provider/channel/threshold tersimpan (14.1)
 * - Halaman status market render (14.3)
 *
 * Memakai user seed 'admin' (role=admin, pwd 'admin') + user dummy non-admin.
 */
class SettingsCest
{
    private int $testUserId = 0;

    public function _before(\FunctionalTester $I)
    {
        $admin = \app\models\User::findByUsername('admin');
        if ($admin === null || !$admin->isAdmin()) {
            throw new \RuntimeException('Seeded admin (role=admin) diperlukan. Jalankan php scripts/seed-admin.php');
        }

        $user = new \app\models\User();
        $user->username = 'plain_' . uniqid();
        $user->email = 'plain_' . uniqid() . '@example.com';
        $user->status = \app\models\User::STATUS_ACTIVE;
        $user->role = \app\models\User::ROLE_USER;
        $user->setPassword('Password123');
        $user->generateAuthKey();
        $user->save(false);
        $this->testUserId = (int) $user->id;

        $this->loginAs($I, 'admin', 'admin');
    }

    public function _after(\FunctionalTester $I)
    {
        if ($this->testUserId > 0) {
            \app\models\User::deleteAll(['id' => $this->testUserId]);
        }
        // Bersihkan setting yang dibuat test; jangan hapus kunci lain.
        \app\models\Setting::deleteAll(['key' => 'marketDataProvider']);
    }

    private function loginAs(\FunctionalTester $I, string $username, string $password): void
    {
        \Yii::$app->user->logout();
        $I->amOnRoute('site/login');
        $I->submitForm('#login-form', [
            'LoginForm[username]' => $username,
            'LoginForm[password]' => $password,
        ]);
        $I->see('Logout', 'form');
    }

    public function guestDenied(\FunctionalTester $I)
    {
        \Yii::$app->user->logout();
        $I->amOnRoute('settings/index');
        // guest ditolak (redirect), tidak boleh render form
        $I->dontSeeElement('#settings-form');
    }

    public function nonAdminDenied(\FunctionalTester $I)
    {
        $this->loginAs($I, \app\models\User::findOne($this->testUserId)->username, 'Password123');
        $I->amOnRoute('settings/index');
        $I->seeResponseCodeIs(403);
        $I->dontSeeElement('#settings-form');
    }

    public function adminSeesSettingsForm(\FunctionalTester $I)
    {
        $I->amOnRoute('settings/index');
        $I->see('Settings & Admin', 'h1');
        $I->seeElement('#settings-form');
        $I->see('Market data provider', 'label');
    }

    public function adminSavesSettings(\FunctionalTester $I)
    {
        $I->amOnRoute('settings/index');
        $I->submitForm('#settings-form', [
            'SettingsForm[provider]' => 'csv',
            'SettingsForm[channelApp]' => 1,
            'SettingsForm[channelEmail]' => 0,
            'SettingsForm[channelTelegram]' => 0,
            'SettingsForm[alertCooldownHours]' => 12,
            'SettingsForm[apiRateLimitPerMinute]' => 200,
            'SettingsForm[historyDays]' => 300,
            'SettingsForm[buyRatio1]' => 0.75,
            'SettingsForm[buyRatio2]' => 0.65,
            'SettingsForm[buyRatio3]' => 0.55,
            'SettingsForm[volumeGrowth1]' => 0.60,
            'SettingsForm[volumeGrowth2]' => 0.30,
            'SettingsForm[volumeGrowth3]' => 0.10,
            'SettingsForm[rvol1]' => 2.5,
            'SettingsForm[rvol2]' => 1.6,
            'SettingsForm[rvol3]' => 1.2,
            'SettingsForm[aboveMa20]' => 15,
            'SettingsForm[aboveMa50]' => 10,
        ]);
        $I->seeElement('.alert-success');

        $I->assertEquals(
            'csv',
            \app\models\Setting::find()->select('value')->where(['key' => 'marketDataProvider'])->scalar()
        );
        $I->assertEquals(
            '12',
            \app\models\Setting::find()->select('value')->where(['key' => 'alertCooldownHours'])->scalar()
        );
        // Threshold tersimpan sebagai JSON dengan nilai yang diedit
        $thresholds = json_decode(
            (string) \app\models\Setting::find()->select('value')->where(['key' => 'signalThresholds'])->scalar(),
            true
        );
        $I->assertEquals(0.75, $thresholds['buyRatio'][0]['min'] ?? null);
        $I->assertEquals(2.5, $thresholds['rvol'][0]['min'] ?? null);
    }

    public function statusPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('settings/status');
        $I->see('Status Market', 'h1');
        $I->see('Provider aktif', 'th');
        $I->see('Saham', 'th');
    }
}