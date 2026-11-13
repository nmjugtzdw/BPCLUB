<?php

declare(strict_types=1);

namespace App;

/**
 * Double-submit CSRF token.
 *
 * The token lives in the session and is compared with hash_equals; a request
 * without a match is refused before any write happens. Tokens are per session
 * (not per request) so multiple tabs keep working.
 */
final class Csrf
{
    private const KEY = 'csrf';

    public function __construct(private readonly SessionStore $session) {}

    public function token(): string
    {
        $existing = $this->session->get(self::KEY);
        if ($existing !== null && strlen($existing) === 64) {
            return $existing;
        }
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::KEY, $token);
        return $token;
    }

    public function validate(mixed $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        $expected = $this->session->get(self::KEY);
        if ($expected === null) {
            return false;
        }
        return hash_equals($expected, $token);
    }

    /** Ready-to-print hidden input for a form. */
    public function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . View::e($this->token()) . '">';
    }
}
