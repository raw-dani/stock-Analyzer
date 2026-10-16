<?php

require __DIR__ . '/../bootstrap-env.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/console.php';
$app = new \yii\console\Application($config);

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
    if ($user->save(false)) {
        echo "SUCCESS: Seeded user '{$username}' (id={$user->id}, role={$user->role})\n";
    } else {
        echo "ERROR: Failed to save user '{$username}'\n";
    }
}

// Akun admin dan demo bawaan
seed('admin', 100, 'Admin123!', 'admin_auth_key_100', '100-token', \app\models\User::ROLE_ADMIN);
seed('demo', 101, 'demo', 'demo_auth_key_101', '101-token', \app\models\User::ROLE_USER);

echo "\n--- Verifikasi Akun ---\n";
$admin = \app\models\User::findByUsername('admin');
if ($admin) {
    echo "Admin ditemukan: ID {$admin->id}, Role {$admin->role}, Status {$admin->status}\n";
    echo "Verifikasi Password 'Admin123!': " . ($admin->validatePassword('Admin123!') ? 'VALID' : 'INVALID') . "\n";
} else {
    echo "ERROR: Admin tidak ditemukan di database!\n";
}
