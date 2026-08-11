<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = [
    'bootstrap/cache',
    'storage/app/private',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
    'storage/logs',
];

foreach ($directories as $directory) {
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);

    if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
        fwrite(STDERR, "No se pudo crear: {$directory}".PHP_EOL);
        exit(1);
    }

    @chmod($path, 0775);
    fwrite(STDOUT, "[OK] {$directory}".PHP_EOL);
}

fwrite(STDOUT, PHP_EOL.'Estructura de almacenamiento preparada.'.PHP_EOL);
