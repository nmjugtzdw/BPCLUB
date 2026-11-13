<?php

declare(strict_types=1);

namespace App;

/**
 * Who is making this request.
 *
 * The session holds a user id only. Role and status are read from the database
 * on every request, so demoting, disabling or restricting an account takes
 * effect immediately instead of surviving in a stale session. Disabled accounts
 * are logged out on sight; restricted accounts stay signed in but their new
 * content goes to the moderation queue.
 */
final class Auth
{
    /** @var array<string, mixed>|null */
    private ?array $user = null;

    private bool $loaded = false;

    public function __construct(
        private readonly SessionStore $session,
        private readonly ?AccountStore $accounts
    ) {
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;
        $this->user = null;

        $raw = $this->session->get('uid');
        if ($raw === null || $this->accounts === null) {
            return null;
        }
        if (preg_match('/^[1-9][0-9]{0,9}$/', $raw) !== 1) {
            $this->session->remove('uid');
            return null;
        }

        $row = $this->accounts->findById((int) $raw);
        if ($row === null || (string) $row['status'] === 'disabled') {
            // The account disappeared or was disabled while the session lived.
            $this->session->remove('uid');
            return null;
        }

        $this->user = $row;
        return $row;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user === null ? null : (int) $user['id'];
    }

    public function name(): string
    {
        $user = $this->user();
        return $user === null ? '' : (string) $user['username'];
    }

    public function isAdmin(): bool
    {
        $user = $this->user();
        return $user !== null
            && (string) $user['role'] === 'admin'
            && (string) $user['status'] === 'active';
    }

    /** 受限账号：可登录，但新发内容需审核后才公开。 */
    public function isRestricted(): bool
    {
        $user = $this->user();
        return $user !== null && (string) $user['status'] === 'restricted';
    }

    public function login(int $userId): void
    {
        // New session id on privilege change: an id captured before login must
        // not become an authenticated session.
        $this->session->regenerate();
        $this->session->set('uid', (string) $userId);
        $this->loaded = false;
        $this->user = null;
    }

    public function logout(): void
    {
        $this->session->remove('uid');
        $this->session->regenerate();
        $this->loaded = false;
        $this->user = null;
    }
}
