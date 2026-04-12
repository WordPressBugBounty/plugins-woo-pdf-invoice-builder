<?php
/**
 * Simple PSR-4 autoloader for Picqer Barcode Generator library.
 * Bundled manually — does NOT modify Composer config.
 */

spl_autoload_register(function ($class) {
    $prefix = 'Picqer\\Barcode\\';
    $baseDir = __DIR__ . '/src/';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
