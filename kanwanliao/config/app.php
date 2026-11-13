<?php

declare(strict_types=1);

/**
 * Default values. Anything environment-specific comes from .env (see .env.example).
 * Empty db.name / db.user means "database not configured" — the app must fail
 * loudly when a database operation is attempted, never silently proceed.
 */
return [
    'app_env' => 'local',
    'app_debug' => false,
    'timezone' => 'UTC',
    'log_path' => 'storage/logs/app.log',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => '',
        'user' => '',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
