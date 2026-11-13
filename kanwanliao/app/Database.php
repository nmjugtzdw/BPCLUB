<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

final class Database
{
    /**
     * Single PDO factory. Prepared statements are real (not emulated), the
     * connection is utf8mb4, and all DATETIME values are interpreted as UTC.
     *
     * @param array<string, mixed> $db
     */
    public static function connect(array $db): PDO
    {
        foreach (['host', 'name', 'user'] as $required) {
            if (!isset($db[$required]) || trim((string) $db[$required]) === '') {
                throw new RuntimeException('Database configuration is incomplete.');
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) $db['host'],
            (int) $db['port'],
            (string) $db['name'],
            (string) ($db['charset'] ?? 'utf8mb4')
        );

        $pdo = new PDO($dsn, (string) $db['user'], (string) ($db['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
