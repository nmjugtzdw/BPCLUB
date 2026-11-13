<?php

declare(strict_types=1);

namespace App;

/**
 * Abuse control for login, registration, publishing, commenting and reporting.
 *
 * Fixed-window counters: simple, predictable and enough to stop scripted
 * hammering without adding a dependency. Callers pass a bucket name and a key
 * (client address, account name); keys are hashed before storage so the table
 * does not become a list of plain IP addresses.
 */
interface RateLimiter
{
    /**
     * Consume one attempt. Returns false when the window is already exhausted.
     */
    public function attempt(string $bucket, string $key, int $limit, int $windowSeconds): bool;

    /** Forget the counters for a key, e.g. after a successful login. */
    public function clear(string $bucket, string $key): void;
}
