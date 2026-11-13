<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

/**
 * PDO implementation of the write side.
 *
 * Status values are checked against an allow-list here as well as in the
 * controllers: a typo or an injected value must never reach an ENUM column and
 * end up as an implicit "empty string" row state in non-strict modes.
 */
final class ContentRepository implements ContentWriter
{
    private const CONTENT_STATUSES = ['pending', 'visible', 'hidden', 'deleted'];

    public function __construct(private readonly PDO $pdo) {}

    public function createThread(
        int $authorId,
        string $category,
        string $title,
        string $body,
        ?int $titleId,
        bool $isSpoiler,
        string $status
    ): int {
        $this->assertStatus($status);
        $stmt = $this->pdo->prepare(
            'INSERT INTO threads (author_id, category, title, body, title_id, is_spoiler, status, created_at, updated_at) '
            . 'VALUES (:author, :category, :title, :body, :title_id, :spoiler, :status, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->bindValue(':author', $authorId, PDO::PARAM_INT);
        $stmt->bindValue(':category', $category, PDO::PARAM_STR);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':body', $body, PDO::PARAM_STR);
        $stmt->bindValue(':title_id', $titleId, $titleId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':spoiler', $isSpoiler ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function createComment(
        int $threadId,
        int $authorId,
        string $body,
        bool $isSpoiler,
        string $status
    ): int {
        $this->assertStatus($status);
        $stmt = $this->pdo->prepare(
            'INSERT INTO comments (thread_id, author_id, body, is_spoiler, status, created_at) '
            . 'VALUES (:thread_id, :author, :body, :spoiler, :status, UTC_TIMESTAMP())'
        );
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_INT);
        $stmt->bindValue(':author', $authorId, PDO::PARAM_INT);
        $stmt->bindValue(':body', $body, PDO::PARAM_STR);
        $stmt->bindValue(':spoiler', $isSpoiler ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function threadForModeration(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, author_id, status FROM threads WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function commentForModeration(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, thread_id, author_id, status FROM comments WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function isThreadVisible(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM threads WHERE id = :id AND status = 'visible' LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function setThreadStatus(int $id, string $status): void
    {
        $this->assertStatus($status);
        $stmt = $this->pdo->prepare('UPDATE threads SET status = :status, updated_at = UTC_TIMESTAMP() WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function setCommentStatus(int $id, string $status): void
    {
        $this->assertStatus($status);
        $stmt = $this->pdo->prepare('UPDATE comments SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function createReport(int $reporterId, string $targetType, int $targetId, string $reason): void
    {
        if (!in_array($targetType, ['thread', 'comment'], true)) {
            throw new RuntimeException('invalid_target_type');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO reports (reporter_id, target_type, target_id, reason, status, created_at) '
            . "VALUES (:reporter, :target_type, :target_id, :reason, 'open', UTC_TIMESTAMP())"
        );
        $stmt->execute([
            ':reporter' => $reporterId,
            ':target_type' => $targetType,
            ':target_id' => $targetId,
            ':reason' => $reason,
        ]);
    }

    public function hasOpenReport(int $reporterId, string $targetType, int $targetId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM reports WHERE reporter_id = :reporter AND target_type = :target_type "
            . "AND target_id = :target_id AND status = 'open' LIMIT 1"
        );
        $stmt->execute([':reporter' => $reporterId, ':target_type' => $targetType, ':target_id' => $targetId]);
        return $stmt->fetchColumn() !== false;
    }

    public function openReports(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $sql = 'SELECT r.id, r.target_type, r.target_id, r.reason, r.created_at, u.username AS reporter_name, '
            . 'c.thread_id AS comment_thread_id '
            . 'FROM reports r JOIN users u ON u.id = r.reporter_id '
            . 'LEFT JOIN comments c ON c.id = r.target_id AND r.target_type = \'comment\' '
            . "WHERE r.status = 'open' ORDER BY r.created_at ASC, r.id ASC LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function resolveReport(int $id, int $handlerId, string $status): void
    {
        if (!in_array($status, ['resolved', 'dismissed'], true)) {
            throw new RuntimeException('invalid_report_status');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE reports SET status = :status, handled_by = :handler, handled_at = UTC_TIMESTAMP() '
            . "WHERE id = :id AND status = 'open'"
        );
        $stmt->execute([':status' => $status, ':handler' => $handlerId, ':id' => $id]);
    }

    public function pendingThreads(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $sql = 'SELECT t.id, t.category, t.title, t.body, t.is_spoiler, t.created_at, u.username AS author_name '
            . 'FROM threads t JOIN users u ON u.id = t.author_id '
            . "WHERE t.status = 'pending' ORDER BY t.created_at ASC, t.id ASC LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function pendingComments(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $sql = 'SELECT c.id, c.thread_id, c.body, c.is_spoiler, c.created_at, '
            . 'u.username AS author_name, t.title AS thread_title '
            . 'FROM comments c JOIN users u ON u.id = c.author_id JOIN threads t ON t.id = c.thread_id '
            . "WHERE c.status = 'pending' ORDER BY c.created_at ASC, c.id ASC LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function audit(int $actorId, string $action, string $targetType, ?int $targetId, array $context = []): void
    {
        $safe = [];
        foreach ($context as $key => $value) {
            // Same rule as the logger: credentials never reach an audit row.
            if (preg_match('/pass|secret|token|cookie|authorization|credential/i', (string) $key) === 1) {
                $safe[$key] = '[redacted]';
                continue;
            }
            $safe[$key] = is_scalar($value) || $value === null ? $value : '[complex]';
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_audit_logs (actor_id, action, target_type, target_id, context, created_at) '
            . 'VALUES (:actor, :action, :target_type, :target_id, :context, UTC_TIMESTAMP())'
        );
        $stmt->bindValue(':actor', $actorId, PDO::PARAM_INT);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':target_type', $targetType, PDO::PARAM_STR);
        $stmt->bindValue(':target_id', $targetId, $targetId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(
            ':context',
            $safe === [] ? null : json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $safe === [] ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $stmt->execute();
    }

    public function recentAudit(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $sql = 'SELECT a.id, a.action, a.target_type, a.target_id, a.context, a.created_at, u.username AS actor_name '
            . 'FROM admin_audit_logs a JOIN users u ON u.id = a.actor_id '
            . 'ORDER BY a.created_at DESC, a.id DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function assertStatus(string $status): void
    {
        if (!in_array($status, self::CONTENT_STATUSES, true)) {
            throw new RuntimeException('invalid_content_status');
        }
    }
}
