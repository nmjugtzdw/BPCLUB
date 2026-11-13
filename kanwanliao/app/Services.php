<?php

declare(strict_types=1);

namespace App;

/**
 * Everything a controller may need for one request, resolved once in Bootstrap.
 *
 * Read and write stores are nullable on purpose: a missing database must not
 * stop the site from rendering its "content unavailable" pages, and must never
 * be mistaken for "the forum is empty". Account, writer and limiter being null
 * simply means login and publishing are unavailable.
 */
final class Services
{
    public function __construct(
        public readonly SessionStore $session,
        public readonly Auth $auth,
        public readonly Csrf $csrf,
        public readonly ?ForumReader $reader = null,
        public readonly ?AccountStore $accounts = null,
        public readonly ?ContentWriter $writer = null,
        public readonly ?RateLimiter $limiter = null
    ) {
    }

    public function canAuthenticate(): bool
    {
        return $this->accounts !== null;
    }

    public function canWrite(): bool
    {
        return $this->writer !== null && $this->accounts !== null;
    }
}
