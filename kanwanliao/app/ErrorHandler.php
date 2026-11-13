<?php

declare(strict_types=1);

namespace App;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    public function __construct(
        private readonly Logger $logger,
        private readonly string $root
    ) {
    }

    public function register(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }

    /**
     * Log the detail, show the visitor a generic message. Stack traces, file
     * paths and configuration values must never reach the response body.
     */
    public function render(Throwable $error): Response
    {
        $this->logger->error('request.failed', [
            'type' => get_class($error),
            'message' => $error->getMessage(),
        ]);

        $body = View::render($this->root, 'status', [
            'code' => 500,
            'title' => '服务暂时不可用',
            'message' => '页面没能正常打开，请稍后再试。',
        ]);

        return Response::html($body, 500);
    }
}
