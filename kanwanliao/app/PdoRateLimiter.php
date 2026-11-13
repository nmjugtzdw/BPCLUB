<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Database-backed fixed-window rate limiter.
 *
 * A single atomic upsert does the counting, so concurrent PHP-FPM workers
 * cannot each read "under the limit" and then all write. Keys are SHA-256
 * hashes of bucket|key with the application-level salt added by the caller.
 */
final class PdoRateLimiter implements RateLimiter
{
    public function __construct(private readonly PDO $pdo) {}

    public function attempt(string $bucket, string $key, int $limit, int $windowSeconds): bool
    {
        $windowSeconds = max(1, $windowSeconds);
        $limit = max(1, $limit);
        $windowStart = gmdate('Y-m-d H:i:s', intdiv(time(), $windowSeconds) * $windowSeconds);
        $hash = hash('sha256', $bucket . '|' . $key);

        // Occasionally drop expired windows so the table stays small.
        if (random_int(1, 50) === 1) {
            $this->pdo
                ->prepare('DELETE FROM rate_limits WHERE window_start < (UTC_TIMESTAMP() - INTERVAL 1 DAY)')
                ->execute();
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (bucket, key_hash, window_start, counter) '
            . 'VALUES (:bucket, :hash, :window_start, 1) '
            . 'ON DUPLICATE KEY UPDATE counter = counter + 1'
        );
        $stmt->execute([':bucket' => $bucket, ':hash' => $hash, ':window_start' => $windowStart]);

        $read = $this->pdo->prepare(
            'SELECT counter FROM rate_limits WHERE bucket = :bucket AND key_hash = :hash AND window_start = :window_start'
        );
        $read->execute([':bucket' => $bucket, ':hash' => $hash, ':window_start' => $windowStart]);
        $counter = (int) $read->fetchColumn();

        return $counter <= $limit;
    }

    public function clear(string $bucket, string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE bucket = :bucket AND key_hash = :hash');
        $stmt->execute([':bucket' => $bucket, ':hash' => hash('sha256', $bucket . '|' . $key)]);
    }
}
