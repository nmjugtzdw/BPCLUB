<?php

declare(strict_types=1);

namespace App;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly string $contentType = 'text/html; charset=utf-8',
        public readonly array $headers = []
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self($status, $body === false ? '{}' : $body, 'application/json; charset=utf-8');
    }

    /**
     * Site-relative redirect only.
     *
     * 303 after a POST makes the browser follow with GET, so a refresh cannot
     * silently resubmit a form. Absolute URLs and protocol-relative paths are
     * refused: they would turn any form into an open redirect.
     */
    public static function redirect(string $location, int $status = 303): self
    {
        if ($location === '' || $location[0] !== '/' || str_starts_with($location, '//')) {
            $location = '/';
        }
        return new self($status, '', 'text/plain; charset=utf-8', ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: ' . $this->contentType);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        // No inline scripts or styles anywhere in the app, so the strict policy
        // costs nothing; it is the main defence if an escaping mistake slips in.
        header(
            "Content-Security-Policy: default-src 'self'; base-uri 'none'; form-action 'self'; "
            . "frame-ancestors 'none'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
            . "object-src 'none'; connect-src 'self'"
        );
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        if ($secure) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
