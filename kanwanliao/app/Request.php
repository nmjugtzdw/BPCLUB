<?php

declare(strict_types=1);

namespace App;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post = [],
        public readonly string $ip = ''
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? rawurldecode($path) : '/';

        if ($path !== '/') {
            $path = '/' . trim($path, '/');
        }

        // Reject anything outside a deliberately narrow path alphabet, and
        // refuse traversal even though dots are otherwise allowed.
        if (str_contains($path, '..') || preg_match('#^[A-Za-z0-9/_\-\.]*$#', $path) !== 1) {
            $path = '/__invalid__';
        }

        /** @var array<string, mixed> $query */
        $query = is_array($_GET) ? $_GET : [];
        /** @var array<string, mixed> $post */
        $post = is_array($_POST) ? $_POST : [];

        // Only the socket peer is trusted. A client-supplied X-Forwarded-For
        // would let anyone forge a fresh rate-limit bucket by changing a header.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return new self($method, $path, $query, $post, $ip);
    }

    /**
     * Build a request without touching superglobals (tests, CLI checks).
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    public static function create(
        string $method,
        string $path,
        array $query = [],
        array $post = [],
        string $ip = '127.0.0.1'
    ): self {
        return new self(strtoupper($method), $path, $query, $post, $ip);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** 表单字段规范化：非字符串一律视为空，去掉首尾空白，压缩连续空白。 */
    public function field(string $key, int $maxLength = 20000): string
    {
        $raw = $this->post[$key] ?? '';
        if (!is_string($raw)) {
            return '';
        }
        $value = trim($raw);
        // A malformed byte sequence (non-UTF-8 client) must not be silently
        // dropped: discard only the invalid bytes, then let validation speak.
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        // Preserve newlines for text areas, collapse runs of spaces only.
        $collapsed = preg_replace('/[ \t]+/u', ' ', $value);
        if ($collapsed !== null) {
            $value = $collapsed;
        }
        return mb_substr($value, 0, $maxLength);
    }

    public function checkbox(string $key): bool
    {
        $raw = $this->post[$key] ?? '';
        return is_string($raw) && in_array($raw, ['1', 'on', 'true', 'yes'], true);
    }
}
