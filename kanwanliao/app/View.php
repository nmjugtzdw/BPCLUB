<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $root, string $name, array $data = [], string $layout = 'layout'): string
    {
        $viewFile = $root . '/resources/views/' . $name . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException('View not found.');
        }
        $content = self::capture($viewFile, $data);

        $layoutFile = $root . '/resources/views/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            return $content;
        }

        return self::capture($layoutFile, array_merge($data, ['content' => $content]));
    }

    /** @param array<string, mixed> $data */
    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } finally {
            $output = ob_get_clean();
        }
        return is_string($output) ? $output : '';
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
