<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class Config
{
    /** @var array<string, mixed> */
    private array $values;

    /** @param array<string, mixed> $values */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function load(string $root): self
    {
        $configFile = $root . '/config/app.php';
        if (!is_file($configFile)) {
            throw new RuntimeException('Configuration file is missing.');
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require $configFile;
        $envFile = $root . '/.env';
        $env = is_file($envFile) ? self::parseEnv($envFile) : [];

        $values = $defaults;
        $values['app_env'] = $env['APP_ENV'] ?? $defaults['app_env'];
        $values['app_debug'] = self::toBool($env['APP_DEBUG'] ?? $defaults['app_debug']);
        $values['timezone'] = $env['TIMEZONE'] ?? $defaults['timezone'];
        $values['log_path'] = $env['LOG_PATH'] ?? $defaults['log_path'];

        $db = $defaults['db'];
        foreach (['host', 'port', 'name', 'user', 'pass', 'charset'] as $key) {
            $envKey = 'DB_' . strtoupper($key);
            if (array_key_exists($envKey, $env)) {
                $db[$key] = $env[$envKey];
            }
        }
        $db['port'] = (int) $db['port'];
        $values['db'] = $db;

        foreach (['app_env', 'timezone', 'log_path'] as $required) {
            if (!is_string($values[$required]) || trim((string) $values[$required]) === '') {
                throw new RuntimeException('Required configuration is empty: ' . $required);
            }
        }

        if (!in_array((string) $values['timezone'], ['UTC'], true)) {
            throw new RuntimeException('TIMEZONE must be UTC for this project.');
        }

        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function db(): array
    {
        return $this->values['db'];
    }

    public function isDebug(): bool
    {
        return (bool) $this->values['app_debug'];
    }

    public function isDbConfigured(): bool
    {
        $db = $this->db();
        return trim((string) $db['name']) !== '' && trim((string) $db['user']) !== '';
    }

    public function absoluteLogPath(string $root): string
    {
        $path = (string) $this->values['log_path'];
        if ($path !== '' && ($path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1)) {
            return $path;
        }
        return rtrim($root, '/\\') . '/' . $path;
    }

    /** @return array<string, string> */
    private static function parseEnv(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('Configuration file could not be read.');
        }

        $out = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $pos));
            $value = trim(substr($trimmed, $pos + 1));
            if (strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
                $value = substr($value, 1, -1);
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
