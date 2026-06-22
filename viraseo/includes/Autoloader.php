<?php
defined('ABSPATH') || exit;
spl_autoload_register(function(string $class): void {
    $prefix = 'ViraSEO\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $file = VIRASEO_DIR . 'includes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require_once $file;
});
