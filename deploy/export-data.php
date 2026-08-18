<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$destination = $argv[1] ?? null;

if (! $destination) {
    fwrite(STDERR, "Uso: php deploy/export-data.php <archivo.sql>\n");
    exit(1);
}

$excluded = [
    'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations',
    'password_reset_tokens', 'sessions',
];
$pdo = DB::connection()->getPdo();
$database = DB::connection()->getDatabaseName();
$handle = fopen($destination, 'wb');

if ($handle === false) {
    fwrite(STDERR, "No se pudo crear el archivo de exportación.\n");
    exit(1);
}

fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
$tables = DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [$database, 'BASE TABLE']);

foreach ($tables as $tableRow) {
    $table = $tableRow->TABLE_NAME;

    if (in_array($table, $excluded, true)) {
        continue;
    }

    $quotedTable = '`'.str_replace('`', '``', $table).'`';
    fwrite($handle, "\nTRUNCATE TABLE {$quotedTable};\n");

    DB::table($table)->orderBy(DB::raw('1'))->chunk(250, function ($rows) use ($handle, $pdo, $quotedTable): void {
        foreach ($rows as $row) {
            $values = (array) $row;
            $columns = implode(', ', array_map(fn (string $column): string => '`'.str_replace('`', '``', $column).'`', array_keys($values)));
            $encoded = implode(', ', array_map(function ($value) use ($pdo): string {
                if ($value === null) {
                    return 'NULL';
                }

                return $pdo->quote((string) $value);
            }, array_values($values)));

            fwrite($handle, "INSERT INTO {$quotedTable} ({$columns}) VALUES ({$encoded});\n");
        }
    });
}

fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);

fwrite(STDOUT, "Exportación completada.\n");
