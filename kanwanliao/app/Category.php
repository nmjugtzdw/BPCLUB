<?php

declare(strict_types=1);

namespace App;

/**
 * Single source for the four discussion categories.
 *
 * The database stores the English slug in an ENUM; every user-facing name and
 * every URL lives here so a label can never drift from its slug.
 */
final class Category
{
    public const NAMES = [
        'movie' => '电影',
        'series' => '电视剧',
        'recommend' => '求推荐',
        'chat' => '闲聊',
    ];

    /** @return array<int, string> */
    public static function slugs(): array
    {
        return array_keys(self::NAMES);
    }

    public static function exists(string $slug): bool
    {
        return isset(self::NAMES[$slug]);
    }

    public static function name(string $slug): string
    {
        return self::NAMES[$slug] ?? '其他';
    }

    /** @return array<string, string> slug => display name */
    public static function all(): array
    {
        return self::NAMES;
    }
}
