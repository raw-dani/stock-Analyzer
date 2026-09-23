<?php

use yii\db\Connection;
use yii\base\Event;

// Konfigurasi shared laravel-style: config/db.php (Modul 15) + env-driven queue.

/**
 * Queue driver (Modul 15.3) — dipilih via env `QUEUE_DRIVER`:
 *   - sync   : eksekusi inline (default dev) — \yii\queue\sync\Queue
 *   - redis  : push ke Redis, jalankan worker `php yii queue/listen` — memakai
 *              yii2-queue redis driver (butuh ekstensi php-redis + server Redis).
 *   - file   : driver file-based (fallback non-blocking tanpa Redis).
 */
function buildQueueComponent(): array
{
    $driver = getenv('QUEUE_DRIVER') ?: 'sync';

    return match ($driver) {
        'redis' => [
            'class' => \yii\queue\redis\Queue::class,
            'redis' => [
                'class' => 'yii\redis\Connection',
                'hostname' => getenv('REDIS_HOST') ?: '127.0.0.1',
                'port' => (int) (getenv('REDIS_PORT') ?: 6379),
                'database' => (int) (getenv('REDIS_DB') ?: 0),
                'password' => getenv('REDIS_PASSWORD') ?: null,
            ],
            'channel' => getenv('QUEUE_CHANNEL') ?: 'queue',
            'ttr' => 60,
            'attempts' => 3,
        ],
        'file' => [
            'class' => \yii\queue\file\Queue::class,
            'path' => '@runtime/queue',
            'ttr' => 60,
            'attempts' => 3,
        ],
        'sync' => [
            'class' => \yii\queue\sync\Queue::class,
        ],
        default => ['class' => \yii\queue\sync\Queue::class],
    };
}

// Konfigurasi yang dipakai ulang oleh web.php & console.php (Modul 15).
return [
    'db' => require __DIR__ . '/db.php',
    'queue' => buildQueueComponent(),
];