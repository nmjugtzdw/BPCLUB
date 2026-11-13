<?php

declare(strict_types=1);

use App\Config;
use App\Database;

/**
 * Versioned migration runner. CLI only; this directory is outside public/.
 *
 * Usage:
 *   php scripts/migrate.php status          列出所有版本及状态
 *   php scripts/migrate.php up              按文件名顺序应用全部未应用版本
 *   php scripts/migrate.php down            回滚最后一个已应用版本
 *   php scripts/migrate.php down <version>  回滚指定版本（会先回滚其后的版本）
 *
 * 版本 = database/migrations 下 `.sql` 文件名去掉扩展名；对应回滚文件为
 * `<version>.down.sql`。文件名前缀决定顺序，不要重命名已应用过的版本。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/app/Config.php';
require $root . '/app/Database.php';

$command = $argv[1] ?? 'status';
if (!in_array($command, ['status', 'up', 'down'], true)) {
    fwrite(STDERR, "用法: php scripts/migrate.php status|up|down [version]\n");
    exit(2);
}

try {
    $config = Config::load($root);
    // CLI 不经过 Bootstrap，必须自己设时区；否则服务器 php.ini 的 PRC 会让
    // 应用层时间与库内 UTC 时间错开 8 小时。
    date_default_timezone_set('UTC');

    $db = $config->db();
    $dbName = (string) $db['name'];

    // Hard gates: never touch a production database or a privileged account.
    if (preg_match('/(_test|_dev|_dev_test)$/', $dbName) !== 1) {
        fwrite(STDERR, "拒绝执行：DB_NAME 必须是隔离测试库（以 _test / _dev 结尾）。\n");
        exit(1);
    }
    if (strtolower((string) $db['user']) === 'root') {
        fwrite(STDERR, "拒绝执行：迁移不得使用 root 账号，请使用专用最低权限账号。\n");
        exit(1);
    }
    if (!$config->isDbConfigured()) {
        fwrite(STDERR, "拒绝执行：数据库配置不完整，请检查 .env。\n");
        exit(1);
    }

    $versions = discoverVersions($root);
    if ($versions === []) {
        fwrite(STDERR, "拒绝执行：没有找到任何迁移文件。\n");
        exit(1);
    }

    $pdo = Database::connect($db);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations ('
        . 'version VARCHAR(64) NOT NULL PRIMARY KEY, '
        . 'applied_at DATETIME NOT NULL'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $applied = appliedVersions($pdo);

    if ($command === 'status') {
        echo '数据库: ' . $dbName . "\n";
        foreach ($versions as $version) {
            echo $version . ': ' . (in_array($version, $applied, true) ? '已应用' : '未应用') . "\n";
        }
        exit(0);
    }

    if ($command === 'up') {
        $pending = array_values(array_diff($versions, $applied));
        if ($pending === []) {
            echo "全部版本已应用，跳过。\n";
            exit(0);
        }
        foreach ($pending as $version) {
            runFile($pdo, $root . '/database/migrations/' . $version . '.sql');
            $insert = $pdo->prepare(
                'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, UTC_TIMESTAMP())'
            );
            $insert->execute([':version' => $version]);
            echo $version . " 已应用。\n";
        }
        exit(0);
    }

    // down：默认回滚最后一个已应用版本；也可指定版本，其后的一并回滚。
    if ($applied === []) {
        echo "没有已应用的版本，无需回滚。\n";
        exit(0);
    }
    $target = $argv[2] ?? null;
    $toRollback = [];
    if ($target === null) {
        $toRollback = [end($applied)];
    } else {
        if (!in_array($target, $applied, true)) {
            fwrite(STDERR, "拒绝执行：版本 " . $target . " 未应用或不存在。\n");
            exit(1);
        }
        $index = array_search($target, $applied, true);
        $toRollback = array_reverse(array_slice($applied, (int) $index));
    }

    foreach ($toRollback as $version) {
        $downFile = $root . '/database/migrations/' . $version . '.down.sql';
        if (!is_file($downFile)) {
            fwrite(STDERR, "拒绝执行：缺少回滚文件 " . $version . ".down.sql。\n");
            exit(1);
        }
        runFile($pdo, $downFile);
        $delete = $pdo->prepare('DELETE FROM schema_migrations WHERE version = :version');
        $delete->execute([':version' => $version]);
        echo $version . " 已回滚。\n";
    }
} catch (Throwable $error) {
    // No DSN, account or credential values in CLI output.
    fwrite(STDERR, '迁移失败: ' . get_class($error) . ' - ' . $error->getMessage() . "\n");
    exit(1);
}

/** @return array<int, string> */
function discoverVersions(string $root): array
{
    $files = glob($root . '/database/migrations/*.sql') ?: [];
    $versions = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (str_ends_with($name, '.down.sql')) {
            continue;
        }
        $versions[] = substr($name, 0, -4);
    }
    sort($versions, SORT_STRING);
    return $versions;
}

/** @return array<int, string> 已应用版本，按应用顺序 */
function appliedVersions(PDO $pdo): array
{
    $rows = $pdo->query('SELECT version FROM schema_migrations ORDER BY version ASC')->fetchAll();
    return array_map(static fn (array $row): string => (string) $row['version'], $rows);
}

function runFile(PDO $pdo, string $file): void
{
    foreach (splitSql((string) file_get_contents($file)) as $statement) {
        $pdo->exec($statement);
    }
}

/** @return array<int, string> */
function splitSql(string $sql): array
{
    $out = [];
    $buffer = '';
    foreach (explode("\n", $sql) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with($trimmed, ';')) {
            $out[] = trim($buffer);
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        $out[] = trim($buffer);
    }
    return $out;
}
