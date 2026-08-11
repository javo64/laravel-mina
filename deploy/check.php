<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requiredExtensions = ['ctype', 'fileinfo', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'tokenizer'];
$requiredFiles = ['artisan', 'composer.json', 'config/view.php', 'public/index.php', 'vendor/autoload.php', '.env'];
$writableDirectories = ['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'];
$failed = false;

echo 'PHP '.PHP_VERSION.PHP_EOL;
if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    echo '[ERROR] Se requiere PHP 8.3 o superior.'.PHP_EOL;
    $failed = true;
}

foreach ($requiredExtensions as $extension) {
    $ok = extension_loaded($extension);
    echo ($ok ? '[OK] ' : '[ERROR] ')."Extensión {$extension}".PHP_EOL;
    $failed = $failed || ! $ok;
}

foreach ($requiredFiles as $file) {
    $ok = is_file($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file));
    echo ($ok ? '[OK] ' : '[ERROR] ').$file.PHP_EOL;
    $failed = $failed || ! $ok;
}

foreach ($writableDirectories as $directory) {
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);
    $ok = is_dir($path) && is_writable($path);
    echo ($ok ? '[OK] ' : '[ERROR] ')."Escritura en {$directory}".PHP_EOL;
    $failed = $failed || ! $ok;
}

exit($failed ? 1 : 0);
