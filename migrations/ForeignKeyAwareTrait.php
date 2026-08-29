<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Helper untuk menambahkan FK secara aman:
 * SQLite tidak mendukung ALTER TABLE ADD FOREIGN KEY, jadi FK hanya
 * ditambahkan pada driver selain sqlite. Pada SQLite integritas referensial
 * dikelola di layer model (relasi AR + unique index + validasi).
 */
trait ForeignKeyAwareTrait
{
    private function addFkCompat(
        string $name,
        string $table,
        array|string $columns,
        string $refTable,
        array|string $refColumns,
        string $delete = 'CASCADE'
    ): void {
        $driver = $this->db->getDriverName();
        if ($driver === 'sqlite') {
            return; // FK tidak didukung via ALTER TABLE; lewati di SQLite
        }
        $this->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, 'CASCADE');
    }
}
