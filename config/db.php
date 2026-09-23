<?php

use yii\db\Connection;
use yii\base\Event;

// Aktifkan enforcement foreign key untuk SQLite (default OFF).
// Pada MySQL/PostgreSQL FK sudah di-enforce oleh server.
Event::on(Connection::class, Connection::EVENT_AFTER_OPEN, function ($event) {
    if ($event->sender->getDriverName() === 'sqlite') {
        $event->sender->createCommand('PRAGMA foreign_keys = ON')->execute();
    }
});

/**
 * Koneksi DB berbasis lingkungan (Modul 15.1).
 *
 * Driver dipilih via env `DB_DRIVER`: sqlite (default/dev) | mysql | pgsql.
 *  - sqlite : DB_DSN  -> sqlite:@app/data/stocks.db  (lokasi file)
 *  - mysql  : mysql:host=DB_HOST;port=DB_PORT;dbname=DB_NAME
 *  - pgsql  : pgsql:host=DB_HOST;port=DB_PORT;dbname=DB_NAME
 * Credential dari DB_USER / DB_PASSWORD. Di docker-compose nilai ini di-set
 * dari variabel lingkungan (lihat deploy/docker-compose.yml).
 *
 * Semua migrasi bersifat cross-driver karena FK ditambah via trait
 * ForeignKeyAwareTrait::addFkCompat() yang skip di SQLite dan aktif di
 * MySQL/PostgreSQL. Silakan jalankan: `php yii migrate/up`.
 */
$driver = getenv('DB_DRIVER') ?: 'sqlite';

switch ($driver) {
    case 'mysql':
        $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1')
            . ';port=' . (getenv('DB_PORT') ?: '3306')
            . ';dbname=' . (getenv('DB_NAME') ?: 'stock_analyzer');
        $charset = 'utf8mb4';
        break;
    case 'pgsql':
        $dsn = 'pgsql:host=' . (getenv('DB_HOST') ?: '127.0.0.1')
            . ';port=' . (getenv('DB_PORT') ?: '5432')
            . ';dbname=' . (getenv('DB_NAME') ?: 'stock_analyzer');
        $charset = '';
        break;
    case 'sqlite':
    default:
        $dsn = getenv('DB_DSN') ?: 'sqlite:@app/data/stocks.db';
        $charset = '';
        break;
}

$config = [
    'class' => 'yii\db\Connection',
    'dsn' => $dsn,
];

if (in_array($driver, ['mysql', 'pgsql'], true)) {
    $config['username'] = getenv('DB_USER') ?: ($driver === 'mysql' ? 'root' : 'postgres');
    $config['password'] = getenv('DB_PASSWORD') ?: '';
    if ($charset !== '') {
        $config['charset'] = $charset;
    }
    // Schema cache untuk produksi
    $config['enableSchemaCache'] = (bool) (getenv('DB_SCHEMA_CACHE') ?: '0');
    $config['schemaCacheDuration'] = 60;
    $config['schemaCache'] = 'cache';
}

return $config;

