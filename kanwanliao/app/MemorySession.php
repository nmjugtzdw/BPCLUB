<?php

declare(strict_types=1);

namespace App;

/**
 * In-memory session for tests and CLI scripts. Behaviour matches NativeSession
 * without touching cookies, so auth flows can be exercised deterministically.
 */
final class MemorySession implements SessionStore
{
    /** @var array<string, string> */
    private array $data = [];

    private int $regenerations = 0;

    public function get(string $key): ?string
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }

    public function regenerationCount(): int
    {
        return $this->regenerations;
    }
}
