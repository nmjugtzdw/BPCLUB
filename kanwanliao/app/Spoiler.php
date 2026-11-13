<?php

declare(strict_types=1);

namespace App;

/**
 * Single rule source for spoiler-safe output.
 *
 * Contract (see docs/P01-内容契约与原型说明.md):
 * - A spoiler body must never be returned to a public render path until the
 *   reader explicitly asks to reveal it.
 * - Lists, titles, summaries, search results, share meta and no-JS output all
 *   go through this class. Hiding the text with CSS after printing it is a
 *   violation, not an implementation.
 * - P03/P04 must enforce this at the query/repository layer too; this class is
 *   the last gate before rendering, not a substitute for server-side filtering.
 */
final class Spoiler
{
    public const NOTICE = '这段内容包含剧透，展开后可见。';

    public static function publicText(string $body, bool $isSpoiler, bool $revealed = false): string
    {
        return $isSpoiler && !$revealed ? self::NOTICE : $body;
    }

    public static function summary(string $body, bool $isSpoiler, int $width = 80): string
    {
        if ($isSpoiler) {
            return self::NOTICE;
        }
        $flat = trim((string) preg_replace('/\s+/u', ' ', strip_tags($body)));
        return mb_strimwidth($flat, 0, $width, '…', 'UTF-8');
    }

    public static function shareDescription(string $body, bool $isSpoiler): string
    {
        return self::summary($body, $isSpoiler, 120);
    }
}
