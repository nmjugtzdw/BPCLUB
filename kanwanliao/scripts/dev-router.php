<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in development server only.
 * Production uses an nginx try_files rule (see README) instead.
 *
 * Usage: php -S 127.0.0.1:8099 -t public scripts/dev-router.php
 */
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) && $path !== '' ? $path : '/';

$public = realpath(__DIR__ . '/../public');

if ($public !== false && $path !== '/') {
    $candidate = realpath($public . $path);
    $insidePublic = $candidate !== false
        && str_starts_with($candidate, $public . DIRECTORY_SEPARATOR);
    if ($insidePublic && is_file($candidate)) {
        return false; // Let the built-in server serve the static asset.
    }
}

require __DIR__ . '/../public/index.php';
