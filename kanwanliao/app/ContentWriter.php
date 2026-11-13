<?php

declare(strict_types=1);

namespace App;

/**
 * Write side of the forum: content creation, moderation and audit.
 *
 * Every method takes already-validated input. Authorisation is decided by the
 * controllers (who may post, who may moderate) — this layer enforces the
 * database-level rules it can, such as refusing to publish into a thread that
 * is not visible.
 */
interface ContentWriter
{
    /** @return int 新帖 id */
    public function createThread(
        int $authorId,
        string $category,
        string $title,
        string $body,
        ?int $titleId,
        bool $isSpoiler,
        string $status
    ): int;

    /** @return int 新评论 id */
    public function createComment(
        int $threadId,
        int $authorId,
        string $body,
        bool $isSpoiler,
        string $status
    ): int;

    /** @return array<string, mixed>|null id, author_id, status */
    public function threadForModeration(int $id): ?array;

    /** @return array<string, mixed>|null id, thread_id, author_id, status */
    public function commentForModeration(int $id): ?array;

    /** 只有 visible 的帖子可被评论 */
    public function isThreadVisible(int $id): bool;

    public function setThreadStatus(int $id, string $status): void;

    public function setCommentStatus(int $id, string $status): void;

    public function createReport(int $reporterId, string $targetType, int $targetId, string $reason): void;

    public function hasOpenReport(int $reporterId, string $targetType, int $targetId): bool;

    /** @return array<int, array<string, mixed>> */
    public function openReports(int $limit = 50): array;

    public function resolveReport(int $id, int $handlerId, string $status): void;

    /** @return array<int, array<string, mixed>> */
    public function pendingThreads(int $limit = 50): array;

    /** @return array<int, array<string, mixed>> */
    public function pendingComments(int $limit = 50): array;

    /** @param array<string, mixed> $context 不得包含密码、令牌等敏感值 */
    public function audit(int $actorId, string $action, string $targetType, ?int $targetId, array $context = []): void;

    /** @return array<int, array<string, mixed>> */
    public function recentAudit(int $limit = 50): array;
}
