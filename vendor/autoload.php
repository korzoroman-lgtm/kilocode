<?php

declare(strict_types=1);

/**
 * Simple PSR-4 Autoloader
 */

spl_autoload_register(function (string $class): void {
    // Project namespace prefix
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/';

    // Check if class uses our namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get relative class name
    $relativeClass = substr($class, $len);

    // Convert namespace separators to directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // If file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Also try to load Composer autoloader if it exists
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload) && $composerAutoload !== __FILE__) {
    require_once $composerAutoload;
}
