<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Read-only access to public forum content.
 *
 * Spoiler contract at the query layer: a spoiler body is never selected unless
 * the caller explicitly asks to reveal it. Titles of spoiler threads are
 * replaced server-side, and spoiler threads are excluded from search so a hit
 * cannot become an oracle for hidden text.
 */
final class ForumReadRepository implements ForumReader
{
    public function __construct(private readonly PDO $pdo) {}

    // ---------------------------------------------------------------- threads

    /** @return array<int, array<string, mixed>> */
    public function threads(?string $category, string $query, int $page, int $pageSize = 20): array
    {
        if ($category !== null && !Category::exists($category)) {
            return [];
        }
        $pageSize = max(1, min($pageSize, 50));
        $page = max(1, $page);
        [$where, $values] = $this->threadConditions($category, $query);

        $sql = 'SELECT t.id, t.category, '
            . "CASE WHEN t.is_spoiler = 0 THEN t.title ELSE '含剧透的讨论' END AS title, "
            . "t.title_id, t.is_spoiler, t.created_at, "
            . 'CASE WHEN t.is_spoiler = 0 THEN t.body ELSE NULL END AS public_body, '
            . 'w.name AS work_name, w.work_type, w.release_year '
            . 'FROM threads t JOIN users u ON u.id = t.author_id '
            . 'LEFT JOIN titles w ON w.id = t.title_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' ORDER BY t.created_at DESC, t.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countThreads(?string $category, string $query): int
    {
        if ($category !== null && !Category::exists($category)) {
            return 0;
        }
        [$where, $values] = $this->threadConditions($category, $query);
        $sql = 'SELECT COUNT(*) FROM threads t JOIN users u ON u.id = t.author_id '
            . 'LEFT JOIN titles w ON w.id = t.title_id WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * One thread with its body.
     *
     * $revealSpoiler is the reader's explicit request to see spoiler text; when
     * false the body column is NULL at the SQL level, so no downstream view can
     * accidentally print it.
     *
     * @return array<string, mixed>|null
     */
    public function thread(int $id, bool $revealSpoiler = false): ?array
    {
        $bodyExpr = $revealSpoiler
            ? 't.body'
            : 'CASE WHEN t.is_spoiler = 0 THEN t.body ELSE NULL END';

        $sql = 'SELECT t.id, t.category, t.title_id, t.is_spoiler, t.created_at, t.updated_at, '
            . "CASE WHEN t.is_spoiler = 0 THEN t.title ELSE '含剧透的讨论' END AS public_title, "
            . 't.title AS raw_title, ' . $bodyExpr . ' AS body, '
            . 'u.username AS author_name, '
            . 'w.name AS work_name, w.work_type, w.release_year '
            . 'FROM threads t JOIN users u ON u.id = t.author_id '
            . 'LEFT JOIN titles w ON w.id = t.title_id '
            . "WHERE t.id = :id AND t.status = 'visible' AND u.status = 'active' LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Visible comments of a thread, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function comments(int $threadId, bool $revealSpoiler = false): array
    {
        $bodyExpr = $revealSpoiler
            ? 'c.body'
            : 'CASE WHEN c.is_spoiler = 0 THEN c.body ELSE NULL END';

        $sql = 'SELECT c.id, c.is_spoiler, c.created_at, ' . $bodyExpr . ' AS body, '
            . 'u.username AS author_name '
            . 'FROM comments c JOIN users u ON u.id = c.author_id '
            . "WHERE c.thread_id = :thread_id AND c.status = 'visible' AND u.status = 'active' "
            . 'ORDER BY c.created_at ASC, c.id ASC LIMIT 200';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countComments(int $threadId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM comments c JOIN users u ON u.id = c.author_id '
            . "WHERE c.thread_id = :thread_id AND c.status = 'visible' AND u.status = 'active'"
        );
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // ----------------------------------------------------------------- titles

    /** @return array<string, mixed>|null */
    public function title(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, work_type, release_year, synopsis, source_label, last_verified_at '
            . 'FROM titles WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Works list, optionally filtered by name. Same-name works stay separate
     * rows: year and type are what tell them apart.
     *
     * @return array<int, array<string, mixed>>
     */
    public function titles(string $query, int $page, int $pageSize = 20): array
    {
        $pageSize = max(1, min($pageSize, 50));
        $page = max(1, $page);
        [$where, $values] = $this->titleConditions($query);

        $sql = 'SELECT w.id, w.name, w.work_type, w.release_year, w.synopsis, w.source_label, '
            . '(SELECT COUNT(*) FROM threads t JOIN users u ON u.id = t.author_id '
            . " WHERE t.title_id = w.id AND t.status = 'visible' AND u.status = 'active') AS thread_count "
            . 'FROM titles w '
            . ($where === '' ? '' : 'WHERE ' . $where . ' ')
            . 'ORDER BY w.name ASC, w.release_year DESC, w.id ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countTitles(string $query): int
    {
        [$where, $values] = $this->titleConditions($query);
        $sql = 'SELECT COUNT(*) FROM titles w ' . ($where === '' ? '' : 'WHERE ' . $where);
        $stmt = $this->pdo->prepare($sql);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function threadsForTitle(int $titleId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        $sql = 'SELECT t.id, t.category, '
            . "CASE WHEN t.is_spoiler = 0 THEN t.title ELSE '含剧透的讨论' END AS title, "
            . "t.is_spoiler, t.created_at, CASE WHEN t.is_spoiler = 0 THEN t.body ELSE NULL END AS public_body, "
            . 'u.username AS author_name '
            . 'FROM threads t JOIN users u ON u.id = t.author_id '
            . "WHERE t.title_id = :title_id AND t.status = 'visible' AND u.status = 'active' "
            . 'ORDER BY t.created_at DESC, t.id DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':title_id', $titleId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------------- search

    /**
     * Case-insensitive search over safe (non-spoiler) thread text and work names.
     *
     * @return array{threads: array<int, array<string, mixed>>, titles: array<int, array<string, mixed>>, threadTotal: int}
     */
    public function search(string $query, int $page, int $pageSize = 10): array
    {
        $pageSize = max(1, min($pageSize, 50));
        $page = max(1, $page);
        $total = $this->countThreads(null, $query);

        return [
            'threads' => $query === '' ? [] : $this->threads(null, $query, $page, $pageSize),
            'titles' => $query === '' ? [] : $this->titles($query, 1, 10),
            'threadTotal' => $query === '' ? 0 : $total,
        ];
    }

    // ---------------------------------------------------------------- authors

    /** @return array<string, mixed>|null */
    public function userProfile(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, created_at FROM users WHERE id = :id AND status <> :disabled LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':disabled', 'disabled', PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function threadsByAuthor(int $id, int $page, int $pageSize = 20): array
    {
        $pageSize = max(1, min($pageSize, 50));
        $page = max(1, $page);
        $sql = 'SELECT t.id, t.category, '
            . "CASE WHEN t.is_spoiler = 0 THEN t.title ELSE '含剧透的讨论' END AS title, "
            . 't.title_id, t.is_spoiler, t.created_at, '
            . 'CASE WHEN t.is_spoiler = 0 THEN t.body ELSE NULL END AS public_body, '
            . 'u.username AS author_name, w.name AS work_name, w.work_type, w.release_year '
            . 'FROM threads t JOIN users u ON u.id = t.author_id '
            . 'LEFT JOIN titles w ON w.id = t.title_id '
            . "WHERE t.author_id = :author AND t.status = 'visible' AND u.status = 'active' "
            . 'ORDER BY t.created_at DESC, t.id DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':author', $id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countThreadsByAuthor(int $id): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM threads t JOIN users u ON u.id = t.author_id '
            . "WHERE t.author_id = :author AND t.status = 'visible' AND u.status = 'active'"
        );
        $stmt->bindValue(':author', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array{0: array<int, string>, 1: array<string, string>}
     */
    private function threadConditions(?string $category, string $query): array
    {
        $conditions = ["t.status = 'visible'", "u.status = 'active'"];
        $values = [];
        if ($category !== null) {
            $conditions[] = 't.category = :category';
            $values[':category'] = $category;
        }
        if ($query !== '') {
            // Search only safe titles and non-spoiler bodies: matches themselves
            // must not become an oracle for hidden spoiler text.
            $conditions[] = "(t.is_spoiler = 0 AND (t.title LIKE :title ESCAPE '!' OR t.body LIKE :body ESCAPE '!'))";
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
            $values[':title'] = $pattern;
            $values[':body'] = $pattern;
        }
        return [$conditions, $values];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function titleConditions(string $query): array
    {
        if ($query === '') {
            return ['', []];
        }
        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
        return ['w.name LIKE :name ESCAPE \'!\'', [':name' => $pattern]];
    }
}
