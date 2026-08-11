<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = DB::connection();
$pdo = $connection->getPdo();
$database = $connection->getDatabaseName();
$target = $argv[1] ?? $root.'/database/mina_cloud.sql';
$targetDirectory = dirname($target);

if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
    fwrite(STDERR, 'No se pudo crear la carpeta del respaldo.'.PHP_EOL);
    exit(1);
}

$handle = fopen($target, 'wb');
if ($handle === false) {
    fwrite(STDERR, 'No se pudo crear el archivo SQL.'.PHP_EOL);
    exit(1);
}

$write = static function (string $sql) use ($handle): void {
    fwrite($handle, $sql);
};

$write("-- Sistema en la nube Mina\n");
$write('-- Respaldo generado: '.date('Y-m-d H:i:s')."\n");
$quotedDatabase = '`'.str_replace('`', '``', $database).'`';
$write("CREATE DATABASE IF NOT EXISTS {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
$write("USE {$quotedDatabase};\n");
$write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

$tables = $pdo->query(
    'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '.$pdo->quote($database).' AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME'
)->fetchAll(PDO::FETCH_COLUMN);

$transientTables = [
    'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs',
    'password_reset_tokens', 'sessions',
];

foreach ($tables as $table) {
    $quotedTable = '`'.str_replace('`', '``', (string) $table).'`';
    $create = $pdo->query('SHOW CREATE TABLE '.$quotedTable)->fetch(PDO::FETCH_ASSOC);
    $createSql = array_values($create)[1] ?? null;

    if (! is_string($createSql)) {
        continue;
    }

    $write("DROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n\n");

    if (in_array($table, $transientTables, true)) {
        continue;
    }

    $statement = $pdo->query('SELECT * FROM '.$quotedTable);
    $columns = null;
    $rows = [];

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $columns ??= array_keys($row);
        $values = array_map(
            static fn ($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
            array_values($row)
        );
        $rows[] = '('.implode(',', $values).')';

        if (count($rows) === 200) {
            $columnSql = implode(',', array_map(static fn (string $column): string => '`'.str_replace('`', '``', $column).'`', $columns));
            $write("INSERT INTO {$quotedTable} ({$columnSql}) VALUES\n".implode(",\n", $rows).";\n");
            $rows = [];
        }
    }

    if ($rows !== [] && $columns !== null) {
        $columnSql = implode(',', array_map(static fn (string $column): string => '`'.str_replace('`', '``', $column).'`', $columns));
        $write("INSERT INTO {$quotedTable} ({$columnSql}) VALUES\n".implode(",\n", $rows).";\n");
    }

    $write("\n");
}

$write("SET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);

fwrite(STDOUT, 'Respaldo creado: '.$target.PHP_EOL);
