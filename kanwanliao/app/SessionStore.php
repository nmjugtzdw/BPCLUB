<?php

declare(strict_types=1);

namespace App;

/**
 * Session storage behind a tiny interface.
 *
 * Controllers and Auth never touch $_SESSION directly: that keeps the request
 * pipeline testable without headers, and makes it visible which values are
 * allowed to live in a session (identifiers and one CSRF token only — never a
 * password, role or status, so a stale session cannot keep old privileges).
 */
interface SessionStore
{
    public function get(string $key): ?string;

    public function set(string $key, string $value): void;

    public function remove(string $key): void;

    /** New session id, same data: called on login and logout to stop fixation. */
    public function regenerate(): void;
}
