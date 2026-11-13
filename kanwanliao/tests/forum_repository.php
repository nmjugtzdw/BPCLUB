<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Category.php';
require dirname(__DIR__) . '/app/ForumReader.php';
require dirname(__DIR__) . '/app/ForumReadRepository.php';

final class RecordingStatement extends PDOStatement
{
    public array $bindings = [];
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bindings[$param] = [$value, $type];
        return true;
    }
    public function execute(?array $params = null): bool { return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
}

final class RecordingConnection extends PDO
{
    public string $sql = '';
    public RecordingStatement $statement;
    public function __construct() { $this->statement = new RecordingStatement(); }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->sql = $query;
        return $this->statement;
    }
}

$pdo = new RecordingConnection();
$repo = new App\ForumReadRepository($pdo);
$repo->threads('movie', '100%_!', 0, 100);
$checks = [
    '仅可见帖子和正常账号' => str_contains($pdo->sql, "t.status = 'visible'") && str_contains($pdo->sql, "u.status = 'active'"),
    '剧透标题与正文不进入公开结果' => str_contains($pdo->sql, "THEN t.title ELSE '含剧透的讨论'") && str_contains($pdo->sql, 'THEN t.body ELSE NULL'),
    '剧透标题正文不参与搜索' => str_contains($pdo->sql, 't.is_spoiler = 0 AND (t.title LIKE'),
    '搜索通配符转义并参数化' => ($pdo->statement->bindings[':title'][0] ?? null) === '%100!%!_!!%',
    '分页限制' => ($pdo->statement->bindings[':limit'][0] ?? null) === 50 && ($pdo->statement->bindings[':offset'][0] ?? null) === 0,
];
$pdo->sql = '';
$repo->threads('invalid', '', 1);
$checks['非法分类不查询'] = $pdo->sql === '';
$failed = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $name . PHP_EOL;
    $failed += $ok ? 0 : 1;
}
echo 'RESULT: ' . (count($checks) - $failed) . ' passed, ' . $failed . ' failed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
