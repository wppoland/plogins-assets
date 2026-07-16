<?php
/**
 * Autoloading: prefer Composer's optimized classmap when present. Fall back to a
 * minimal PSR-4 autoloader so the plugin still boots if vendor/ is absent (the
 * wp.org build ships without Composer).
 *
 * @package PloginsAssets
 */

declare(strict_types=1);

namespace PloginsAssets;

defined('ABSPATH') || exit;

$composer = __DIR__ . '/vendor/autoload.php';
if (is_readable($composer)) {
    require_once $composer;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix  = 'PloginsAssets\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});
