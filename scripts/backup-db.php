<?php

/**
 * Backup DB sederhana (Modul 15.1/Ops).
 *
 * Menyalin file SQLite / melakukan dump untuk MySQL/PostgreSQL ke folder backup
 * dengan timestamp. Dipanggil dari crontab (lihat deploy/crontab.example).
 *
 * Penggunaan:
 *   php scripts/backup-db.php [--dir=/path/backup]
 *
 * Contoh (SQLite):
 *   php scripts/backup-db.php --dir=/var/backups/stock
 * Contoh (MySQL):
 *   DB_DRIVER=mysql DB_HOST=... php scripts/backup-db.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/console.php';
$app = new yii\console\Application($config);

$options = getopt('', ['dir::']);
$dir = ($options['dir'] ?? '') !== '' ? $options['dir'] : $app->getAlias('@app/backups');
if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
    fwrite(STDERR, "Gagal membuat folder backup: {$dir}\n");
    exit(1);
}

$stamp = date('Ymd-His');
$driver = $app->db->getDriverName();
$target = '';

try {
    switch ($driver) {
        case 'sqlite':
            $src = $app->db->dsn; // sqlite:path
            $path = substr($src, 7);
            $path = $app->getAlias($path);
            $target = rtrim($dir, '/') . "/stocks-{$stamp}.db";
            if (!copy($path, $target)) {
                throw new RuntimeException("Gagal menyalin SQLite: {$path}");
            }
            break;

        case 'mysql':
        case 'pgsql':
            $target = rtrim($dir, '/') . "/stocks-{$stamp}.sql";
            $dbName = $app->db->dsn;
            $dbName = preg_match('/dbname=([^;]+)/', $dbName, $m) ? $m[1] : 'stock_analyzer';
            $bin = $driver === 'mysql' ? 'mysqldump' : 'pg_dump';
            $env = 'DB_PASSWORD=' . escapeshellarg((string) $app->db->password);
            $cmd = "{$env} {$bin} "
                . ($driver === 'mysql' ? "-h" . escapeshellarg((string) $app->db->hostname) . ' ' : '')
                . escapeshellarg($dbName);
            $out = [];
            exec($cmd . ' 2>&1', $out, $code);
            if ($code !== 0) {
                throw new RuntimeException("Dump gagal: " . implode("\n", $out));
            }
            file_put_contents($target, implode("\n", $out));
            break;

        default:
            throw new RuntimeException("Driver tidak didukung untuk backup: {$driver}");
    }

    fwrite(STDOUT, "Backup OK -> {$target}\n");
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Backup error: " . $e->getMessage() . "\n");
    exit(1);
}