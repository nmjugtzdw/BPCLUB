<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class Logger
{
    public function __construct(
        private readonly string $path,
        private readonly bool $debug = false
    ) {
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Log directory is not writable.');
        }

        $safe = $this->redact($context);
        $suffix = $safe === []
            ? ''
            : ' ' . json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line = gmdate('Y-m-d\TH:i:s\Z') . ' ' . $level . ' ' . $message . $suffix . PHP_EOL;

        if (file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Log file is not writable.');
        }
    }

    /**
     * Credentials must never reach a log file, even if a caller accidentally
     * passes a whole config array as context.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/pass|secret|token|cookie|authorization|credential/i', (string) $key) === 1) {
                $context[$key] = '[redacted]';
            }
        }
        return $context;
    }
}
