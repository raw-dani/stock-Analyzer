<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/test.php';
$app = new \yii\web\Application($config);

function seed(string $username, int $id, string $password, string $authKey, ?string $accessToken, string $role = 'user'): void
{
    $user = \app\models\User::findByUsername($username);
    if ($user === null) {
        $user = new \app\models\User();
        $user->id = $id;
        $user->username = $username;
        $user->email = $username . '@example.com';
    }
    $user->status = \app\models\User::STATUS_ACTIVE;
    $user->role = $role; // RBAC Modul 14.2
    $user->setPassword($password);
    $user->auth_key = $authKey;
    $user->access_token = $accessToken;
    $user->save(false);
    echo "Seeded user {$username} (id={$user->id}, role={$user->role})\n";
}

// Matches tests/unit/models/UserTest.php & LoginFormTest.php expectations
seed('admin', 100, 'admin', 'test100key', '100-token', \app\models\User::ROLE_ADMIN);
seed('demo', 101, 'demo', 'test101key', '101-token', \app\models\User::ROLE_USER);

// verify
verify('findIdentity(100)', \app\models\User::findIdentity(100)?->username === 'admin');
verify('accessToken', \app\models\User::findIdentityByAccessToken('100-token')?->username === 'admin');
verify('authKey', (\app\models\User::findIdentity(100))->validateAuthKey('test100key'));
verify('demo login', (\app\models\User::findByUsername('demo'))->validatePassword('demo'));

function verify(string $label, bool $cond): void
{
    echo ($cond ? 'OK  ' : 'FAIL') . " $label\n";
}
