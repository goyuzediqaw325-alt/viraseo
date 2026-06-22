<?php
defined('ABSPATH') || exit;

spl_autoload_register(function (string $class): void {
    $prefix = 'ViraSEO\\';
    $base_dir = VIRASEO_DIR . 'includes/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require_once $file;
});
