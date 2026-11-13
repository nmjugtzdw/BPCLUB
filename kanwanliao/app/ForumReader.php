<?php

declare(strict_types=1);

namespace App;

/**
 * Read side of the forum. Controllers depend on this interface so the render
 * path can be tested without a database, and so a future caching or API layer
 * can slot in without touching views.
 */
interface ForumReader
{
    /** @return array<int, array<string, mixed>> */
    public function threads(?string $category, string $query, int $page, int $pageSize = 20): array;

    public function countThreads(?string $category, string $query): int;

    /**
     * @return array<string, mixed>|null
     */
    public function thread(int $id, bool $revealSpoiler = false): ?array;

    /** @return array<int, array<string, mixed>> */
    public function comments(int $threadId, bool $revealSpoiler = false): array;

    public function countComments(int $threadId): int;

    /** @return array<string, mixed>|null */
    public function title(int $id): ?array;

    /** @return array<int, array<string, mixed>> */
    public function titles(string $query, int $page, int $pageSize = 20): array;

    public function countTitles(string $query): int;

    /** @return array<int, array<string, mixed>> */
    public function threadsForTitle(int $titleId, int $limit = 20): array;

    /**
     * @return array{threads: array<int, array<string, mixed>>, titles: array<int, array<string, mixed>>, threadTotal: int}
     */
    public function search(string $query, int $page, int $pageSize = 10): array;

    /** @return array<string, mixed>|null 公开资料：不含邮箱等非公开字段 */
    public function userProfile(int $id): ?array;

    /** @return array<int, array<string, mixed>> 某作者的公开帖子 */
    public function threadsByAuthor(int $id, int $page, int $pageSize = 20): array;

    public function countThreadsByAuthor(int $id): int;
}
