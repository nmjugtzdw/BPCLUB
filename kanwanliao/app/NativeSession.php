<?php

declare(strict_types=1);

namespace App;

/**
 * Native PHP session with the hardening this project relies on.
 *
 * In CLI (tests, scripts) there is no session cookie, so the class degrades to
 * an in-memory array. That keeps `Bootstrap::create()` usable from the test
 * runner without emitting "headers already sent" warnings.
 */
final class NativeSession implements SessionStore
{
    private bool $native = false;

    /** @var array<string, string> */
    private array $memory = [];

    public function __construct()
    {
        $this->start();
    }

    private function start(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent() || session_status() === PHP_SESSION_ACTIVE) {
            $this->native = session_status() === PHP_SESSION_ACTIVE;
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        session_name('kwl_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ]);
        // Reject session ids the server never issued (fixation defence), and
        // keep the id out of URLs.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        if (@session_start()) {
            $this->native = true;
            return;
        }
        $this->native = false;
    }

    public function get(string $key): ?string
    {
        if (!$this->native) {
            return $this->memory[$key] ?? null;
        }
        $value = $_SESSION[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value): void
    {
        if (!$this->native) {
            $this->memory[$key] = $value;
            return;
        }
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        if (!$this->native) {
            unset($this->memory[$key]);
            return;
        }
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        if (!$this->native) {
            return;
        }
        // Delete the old session file too: a fixated id must stop working.
        @session_regenerate_id(true);
    }
}
