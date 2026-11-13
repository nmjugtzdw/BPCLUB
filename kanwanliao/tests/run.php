<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Category;
use App\Config;
use App\Controllers\CategoryController;
use App\Controllers\HomeController;
use App\Controllers\SearchController;
use App\Controllers\ThreadController;
use App\Controllers\TitleController;
use App\Database;
use App\ForumReader;
use App\Logger;
use App\Pager;
use App\Request;
use App\Response;
use App\Router;
use App\Spoiler;
use App\View;

/**
 * Zero-dependency test runner (no PHPUnit / no new packages).
 * Usage: php tests/run.php
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/app/Bootstrap.php';
// FakeReader implements ForumReader and is declared before Bootstrap::create()
// registers the real autoloader, so load the contracts it depends on first.
require $root . '/app/ForumReader.php';
require $root . '/app/Category.php';
require $root . '/app/Pager.php';
require $root . '/app/ReaderUnavailable.php';
// 测试替身在这些接口之后声明，早于 Bootstrap 注册自动加载器，因此显式载入。
require $root . '/app/AccountStore.php';
require $root . '/app/ContentWriter.php';
require $root . '/app/RateLimiter.php';

$passed = 0;
$failed = 0;

function check(string $name, bool $ok): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo 'PASS  ' . $name . PHP_EOL;
    } else {
        $failed++;
        echo 'FAIL  ' . $name . PHP_EOL;
    }
}

function throwsRuntime(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (Throwable $error) {
        return true;
    }
}

/** @param array<string, string> $query */
function request(string $path, array $query = []): Request
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $path . ($query === [] ? '' : '?' . http_build_query($query));
    $_GET = $query;
    return Request::fromGlobals();
}

/**
 * In-memory reader that mirrors ForumReadRepository's spoiler behaviour:
 * a spoiler body is only returned when the caller asks to reveal it, and a
 * spoiler title is replaced with a generic label.
 */
final class FakeReader implements ForumReader
{
    public const SPOILER_BODY = '结尾把那条线索收回来了，凶手是车站管理员';
    public const SPOILER_TITLE = '《风起小城》完结了，结尾你怎么看？';
    public const PLAIN_BODY = '镜头很克制，留白比台词说得多。';

    /** @var array<int, array<string, mixed>> */
    private array $threads;
    /** @var array<int, array<string, mixed>> */
    private array $titles;

    public function __construct()
    {
        $this->titles = [
            ['id' => 1, 'name' => '风起小城（示例作品）', 'work_type' => 'series', 'release_year' => 2026, 'synopsis' => '演示简介', 'source_label' => '演示虚构', 'last_verified_at' => null, 'thread_count' => 2],
            ['id' => 2, 'name' => '雨后的车站（示例作品）', 'work_type' => 'movie', 'release_year' => 2025, 'synopsis' => '演示简介', 'source_label' => '演示虚构', 'last_verified_at' => null, 'thread_count' => 1],
        ];
        $this->threads = [
            ['id' => 2, 'category' => 'movie', 'title' => '雨后的车站很好看', 'title_id' => 2, 'is_spoiler' => 0, 'created_at' => '2026-11-12 18:40:00', 'public_body' => self::PLAIN_BODY, 'author_name' => '示例观众乙', 'work_name' => '雨后的车站（示例作品）', 'work_type' => 'movie', 'release_year' => 2025],
            ['id' => 1, 'category' => 'series', 'title' => '含剧透的讨论', 'title_id' => 1, 'is_spoiler' => 1, 'created_at' => '2026-11-12 20:10:00', 'public_body' => null, 'author_name' => '示例观众甲', 'work_name' => '风起小城（示例作品）', 'work_type' => 'series', 'release_year' => 2026],
        ];
    }

    public function threads(?string $category, string $query, int $page, int $pageSize = 20): array
    {
        $rows = $this->threads;
        if ($category !== null) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['category'] === $category));
        }
        if ($query !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => $r['is_spoiler'] === 0
                    && (str_contains((string) $r['title'], $query) || str_contains((string) $r['public_body'], $query))
            ));
        }
        return array_slice($rows, max(0, ($page - 1) * $pageSize), $pageSize);
    }

    public function countThreads(?string $category, string $query): int
    {
        return count($this->threads($category, $query, 1, 1000));
    }

    public function thread(int $id, bool $revealSpoiler = false): ?array
    {
        foreach ($this->threads as $row) {
            if ($row['id'] !== $id) {
                continue;
            }
            $isSpoiler = $row['is_spoiler'] === 1;
            $row['public_title'] = $isSpoiler ? '含剧透的讨论' : $row['title'];
            $row['raw_title'] = $row['title'];
            $row['body'] = $isSpoiler && !$revealSpoiler ? null : ($isSpoiler ? self::SPOILER_BODY : self::PLAIN_BODY);
            $row['updated_at'] = $row['created_at'];
            return $row;
        }
        return null;
    }

    public function comments(int $threadId, bool $revealSpoiler = false): array
    {
        if ($threadId !== 1) {
            return [];
        }
        return [
            ['id' => 1, 'is_spoiler' => 0, 'created_at' => '2026-11-12 21:00:00', 'body' => '同感，那段我也回看了一遍。', 'author_name' => '示例观众乙'],
            ['id' => 2, 'is_spoiler' => 1, 'created_at' => '2026-11-12 21:10:00', 'body' => $revealSpoiler ? '其实那个配角早在第三集就露过面。' : null, 'author_name' => '示例观众丙'],
        ];
    }

    public function countComments(int $threadId): int
    {
        return $threadId === 1 ? 2 : 0;
    }

    public function title(int $id): ?array
    {
        foreach ($this->titles as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    public function titles(string $query, int $page, int $pageSize = 20): array
    {
        $rows = $this->titles;
        if ($query !== '') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => str_contains((string) $r['name'], $query)));
        }
        return array_slice($rows, max(0, ($page - 1) * $pageSize), $pageSize);
    }

    public function countTitles(string $query): int
    {
        return count($this->titles($query, 1, 1000));
    }

    public function threadsForTitle(int $titleId, int $limit = 20): array
    {
        return array_values(array_filter($this->threads, static fn (array $r): bool => $r['title_id'] === $titleId));
    }

    public function search(string $query, int $page, int $pageSize = 10): array
    {
        return [
            'threads' => $this->threads(null, $query, $page, $pageSize),
            'titles' => $this->titles($query, 1, 10),
            'threadTotal' => $this->countThreads(null, $query),
        ];
    }

    public function userProfile(int $id): ?array
    {
        if ($id !== 1) {
            return null;
        }
        return ['id' => 1, 'username' => '示例观众甲', 'created_at' => '2026-11-12 09:00:00'];
    }

    public function threadsByAuthor(int $id, int $page, int $pageSize = 20): array
    {
        if ($id !== 1) {
            return [];
        }
        $rows = array_values(array_filter($this->threads, static fn (array $r): bool => $r['author_name'] === '示例观众甲'));
        return array_slice($rows, max(0, ($page - 1) * $pageSize), $pageSize);
    }

    public function countThreadsByAuthor(int $id): int
    {
        return count($this->threadsByAuthor($id, 1, 1000));
    }
}

/**
 * 内存账号仓储：注册、登录、状态处置都可测，不依赖数据库。
 */
final class FakeAccounts implements App\AccountStore
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    private int $nextId = 1;

    public function seed(string $username, string $email, string $password, string $role = 'user', string $status = 'active'): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => $status,
            'created_at' => '2026-11-12 09:00:00',
        ];
        return $id;
    }

    public function findById(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function findByIdentity(string $identity): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['username'] === $identity || $row['email'] === $identity) {
                return $row;
            }
        }
        return null;
    }

    public function existsUsername(string $username): bool
    {
        foreach ($this->rows as $row) {
            if ($row['username'] === $username) {
                return true;
            }
        }
        return false;
    }

    public function existsEmail(string $email): bool
    {
        foreach ($this->rows as $row) {
            if ($row['email'] === $email) {
                return true;
            }
        }
        return false;
    }

    public function create(string $username, string $email, string $passwordHash): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => 'user',
            'status' => 'active',
            'created_at' => '2026-11-12 09:00:00',
        ];
        return $id;
    }

    public function setStatus(int $id, string $status): void
    {
        if (isset($this->rows[$id])) {
            $this->rows[$id]['status'] = $status;
        }
    }

    /** @return array<string, mixed> */
    public function row(int $id): array
    {
        return $this->rows[$id] ?? [];
    }
}

/**
 * 内存写入仓储：记录写入了什么，并把状态改动保存下来供断言。
 */
final class FakeWriter implements App\ContentWriter
{
    /** @var array<int, array<string, mixed>> */
    public array $threads = [];
    /** @var array<int, array<string, mixed>> */
    public array $comments = [];
    /** @var array<int, array<string, mixed>> */
    public array $reports = [];
    /** @var array<int, array<string, mixed>> */
    public array $audit = [];
    /** @var array<string, string> "thread:1" => status */
    public array $statuses = [];

    private int $nextThread = 100;
    private int $nextComment = 200;
    private int $nextReport = 300;

    public function createThread(
        int $authorId,
        string $category,
        string $title,
        string $body,
        ?int $titleId,
        bool $isSpoiler,
        string $status
    ): int {
        $id = $this->nextThread++;
        $this->threads[$id] = [
            'id' => $id, 'author_id' => $authorId, 'category' => $category, 'title' => $title,
            'body' => $body, 'title_id' => $titleId, 'is_spoiler' => $isSpoiler, 'status' => $status,
            'created_at' => '2026-11-14 01:00:00',
        ];
        $this->statuses['thread:' . $id] = $status;
        return $id;
    }

    public function createComment(
        int $threadId,
        int $authorId,
        string $body,
        bool $isSpoiler,
        string $status
    ): int {
        $id = $this->nextComment++;
        $this->comments[$id] = [
            'id' => $id, 'thread_id' => $threadId, 'author_id' => $authorId, 'body' => $body,
            'is_spoiler' => $isSpoiler, 'status' => $status, 'created_at' => '2026-11-14 01:00:00',
        ];
        return $id;
    }

    public function threadForModeration(int $id): ?array
    {
        if (!isset($this->threads[$id])) {
            return null;
        }
        return ['id' => $id, 'author_id' => $this->threads[$id]['author_id'], 'status' => $this->threads[$id]['status']];
    }

    public function commentForModeration(int $id): ?array
    {
        if (!isset($this->comments[$id])) {
            return null;
        }
        return [
            'id' => $id,
            'thread_id' => $this->comments[$id]['thread_id'],
            'author_id' => $this->comments[$id]['author_id'],
            'status' => $this->comments[$id]['status'],
        ];
    }

    public function isThreadVisible(int $id): bool
    {
        return ($this->threads[$id]['status'] ?? '') === 'visible';
    }

    public function setThreadStatus(int $id, string $status): void
    {
        if (isset($this->threads[$id])) {
            $this->threads[$id]['status'] = $status;
        }
    }

    public function setCommentStatus(int $id, string $status): void
    {
        if (isset($this->comments[$id])) {
            $this->comments[$id]['status'] = $status;
        }
    }

    public function createReport(int $reporterId, string $targetType, int $targetId, string $reason): void
    {
        $id = $this->nextReport++;
        $this->reports[$id] = [
            'id' => $id, 'reporter_id' => $reporterId, 'target_type' => $targetType,
            'target_id' => $targetId, 'reason' => $reason, 'status' => 'open',
            'created_at' => '2026-11-14 01:00:00', 'reporter_name' => '举报人',
        ];
    }

    public function hasOpenReport(int $reporterId, string $targetType, int $targetId): bool
    {
        foreach ($this->reports as $row) {
            if ($row['reporter_id'] === $reporterId && $row['target_type'] === $targetType
                && $row['target_id'] === $targetId && $row['status'] === 'open') {
                return true;
            }
        }
        return false;
    }

    public function openReports(int $limit = 50): array
    {
        $rows = array_values(array_filter(
            $this->reports,
            static fn (array $r): bool => $r['status'] === 'open'
        ));
        // 与 PDO 实现保持一致：评论举报要带出所属帖子 id。
        foreach ($rows as &$row) {
            $row['comment_thread_id'] = $row['target_type'] === 'comment'
                ? ($this->comments[$row['target_id']]['thread_id'] ?? null)
                : null;
        }
        unset($row);
        return array_slice($rows, 0, $limit);
    }

    public function resolveReport(int $id, int $handlerId, string $status): void
    {
        if (isset($this->reports[$id])) {
            $this->reports[$id]['status'] = $status;
        }
    }

    public function pendingThreads(int $limit = 50): array
    {
        return array_slice(array_values(array_filter(
            $this->threads,
            static fn (array $r): bool => $r['status'] === 'pending'
        )), 0, $limit);
    }

    public function pendingComments(int $limit = 50): array
    {
        return array_slice(array_values(array_filter(
            $this->comments,
            static fn (array $r): bool => $r['status'] === 'pending'
        )), 0, $limit);
    }

    public function audit(int $actorId, string $action, string $targetType, ?int $targetId, array $context = []): void
    {
        $this->audit[] = [
            'actor_id' => $actorId, 'action' => $action, 'target_type' => $targetType,
            'target_id' => $targetId, 'context' => $context, 'actor_name' => '管理员',
            'created_at' => '2026-11-14 01:00:00',
        ];
    }

    public function recentAudit(int $limit = 50): array
    {
        return array_slice(array_reverse($this->audit), 0, $limit);
    }
}

/**
 * 内存限流器：按 (bucket,key) 计数，便于验证超限行为。
 */
final class FakeLimiter implements App\RateLimiter
{
    /** @var array<string, int> */
    private array $counts = [];

    public function attempt(string $bucket, string $key, int $limit, int $windowSeconds): bool
    {
        $slot = $bucket . '|' . $key;
        $this->counts[$slot] = ($this->counts[$slot] ?? 0) + 1;
        return $this->counts[$slot] <= $limit;
    }

    public function clear(string $bucket, string $key): void
    {
        unset($this->counts[$bucket . '|' . $key]);
    }
}

$app = Bootstrap::create($root);
$config = $app->config();
$logger = $app->logger();
$fake = new FakeReader();

// --- Configuration and environment ---
check('配置文件缺失时明确失败', throwsRuntime(static fn () => Config::load($root . '/__missing_config__')));
check('时区固定为 UTC', date_default_timezone_get() === 'UTC');
check('未配置 .env 时数据库状态为未配置', $config->isDbConfigured() === false);
check('配置必需项有默认值', $config->get('app_env') === 'local');

// --- Routing ---
$router = new Router([
    ['GET', '/', ['X', 'index']],
    ['GET', '/thread/{id}', ['X', 'show']],
    ['GET', '/category/{slug}', ['X', 'cat']],
]);
check('路由命中首页', $router->match('GET', '/') !== null);
check('未知路径不匹配', $router->match('GET', '/no-such-page') === null);
check('方法不匹配时返回空', $router->match('POST', '/') === null);
$paramMatch = $router->match('GET', '/thread/42');
check('动态参数被正确捕获', $paramMatch !== null && ($paramMatch['params']['id'] ?? '') === '42');
$catMatch = $router->match('GET', '/category/movie');
check('分类路由捕获 slug', $catMatch !== null && ($catMatch['params']['slug'] ?? '') === 'movie');
check('请求路径规范化', request('/thread/abc/')->path === '/thread/abc');
check('非法字符路径被拒绝', request('/%2e%2e/secret')->path !== '/../secret');

// --- Pager ---
$pager = new Pager(0, 10, 35);
check('页码越界时收敛到第 1 页', $pager->page === 1 && $pager->pages === 4);
check('末页不提供下一页', (new Pager(4, 10, 35))->hasNext() === false);
check('第一页不提供上一页', $pager->hasPrev() === false);
check('空数据集仍显示第 1 页', (new Pager(5, 10, 0))->page === 1 && (new Pager(5, 10, 0))->isEmpty());

// --- Category ---
check('四个分类齐全', Category::slugs() === ['movie', 'series', 'recommend', 'chat']);
check('未知分类不被当作有效值', Category::exists('nope') === false);

// --- Spoiler contract (the hard gate) ---
$secret = FakeReader::SPOILER_BODY;
check('剧透正文默认不进入公开文本', Spoiler::publicText($secret, true) === Spoiler::NOTICE);
check('读者主动展开后才返回正文', Spoiler::publicText($secret, true, true) === $secret);
check('剧透摘要不泄露正文', !str_contains(Spoiler::summary($secret, true), $secret));
check('分享描述不泄露正文', !str_contains(Spoiler::shareDescription($secret, true), $secret));
check('非剧透摘要保留内容', str_contains(Spoiler::summary('镜头很克制，留白比台词说得多', false), '镜头很克制'));
check('摘要去掉 HTML 标签', !str_contains(Spoiler::summary('<b>你好</b> 世界', false), '<b>'));

// --- P03 首页（注入内存仓储，不连库）---
$home = (new HomeController($config, $logger, $root, $fake))->index(request('/'), []);
check('首页返回 200', $home->status === 200);
check('首页含筹备版本声明', str_contains($home->body, '筹备版本'));
check('首页含剧透提示占位', str_contains($home->body, Spoiler::NOTICE));
check('首页 HTML 不含剧透正文', !str_contains($home->body, $secret));
check('首页不展示剧透帖真实标题', !str_contains($home->body, FakeReader::SPOILER_TITLE));
check('首页展示非剧透帖标题', str_contains($home->body, '雨后的车站很好看'));
check('首页分类是可点链接', str_contains($home->body, 'href="/category/movie"'));
check('发布入口已可用', str_contains($home->body, 'href="/publish"'));
check('登录入口已可用', str_contains($home->body, 'href="/login"'));
check('未登录时导航显示注册入口', str_contains($home->body, 'href="/register"'));
check('首页给出帖子总数', str_contains($home->body, '2 个话题'));

// 无仓储时必须 503，不能把「没有服务器」说成「没有内容」
$homeNoDb = (new HomeController($config, $logger, $root, null))->index(request('/'), []);
check('数据库不可用时首页返回 503', $homeNoDb->status === 503);
check('数据库不可用时明确说明读不出来', str_contains($homeNoDb->body, '内容暂时无法显示'));

// --- P03 帖子详情 ---
$thread = (new ThreadController($config, $logger, $root, $fake))->show(request('/thread/1'), ['id' => '1']);
check('帖子详情返回 200', $thread->status === 200);
check('剧透帖默认不含正文', !str_contains($thread->body, $secret));
check('剧透帖默认显示提示与展开入口', str_contains($thread->body, Spoiler::NOTICE) && str_contains($thread->body, '?reveal=1'));
check('剧透评论默认不含正文', !str_contains($thread->body, '那个配角早在第三集'));
check('帖子详情含普通评论', str_contains($thread->body, '同感，那段我也回看了一遍'));

$revealed = (new ThreadController($config, $logger, $root, $fake))->show(request('/thread/1', ['reveal' => '1']), ['id' => '1']);
check('主动展开后才输出剧透正文', str_contains($revealed->body, $secret));
check('展开后剧透评论也可见', str_contains($revealed->body, '那个配角早在第三集'));

$plain = (new ThreadController($config, $logger, $root, $fake))->show(request('/thread/2'), ['id' => '2']);
check('普通帖直接显示正文', str_contains($plain->body, FakeReader::PLAIN_BODY));

$missingThread = (new ThreadController($config, $logger, $root, $fake))->show(request('/thread/999'), ['id' => '999']);
check('不存在的帖子返回 404', $missingThread->status === 404);
$badId = (new ThreadController($config, $logger, $root, $fake))->show(request('/thread/abc'), ['id' => 'abc']);
check('非法帖子 ID 返回 404', $badId->status === 404);
$zeroId = (new ThreadController($config, $logger, $root, $fake))->show(request('/thread/0'), ['id' => '0']);
check('0 号帖子返回 404', $zeroId->status === 404);

// --- P03 分类页 ---
$cat = (new CategoryController($config, $logger, $root, $fake))->index(request('/category/movie'), ['slug' => 'movie']);
check('分类页返回 200 且只含该分类', $cat->status === 200 && str_contains($cat->body, '雨后的车站很好看') && !str_contains($cat->body, FakeReader::SPOILER_TITLE));
check('分类页标记当前分类', str_contains($cat->body, 'aria-current="page"'));
$badCat = (new CategoryController($config, $logger, $root, $fake))->index(request('/category/nope'), ['slug' => 'nope']);
check('未知分类返回 404', $badCat->status === 404);

// --- P03 作品页 ---
$titlePage = (new TitleController($config, $logger, $root, $fake))->show(request('/title/1'), ['id' => '1']);
check('作品页返回 200', $titlePage->status === 200);
check('作品页列出关联讨论且不含剧透正文', str_contains($titlePage->body, '相关讨论') && !str_contains($titlePage->body, $secret));
$missingTitle = (new TitleController($config, $logger, $root, $fake))->show(request('/title/999'), ['id' => '999']);
check('不存在的作品返回 404', $missingTitle->status === 404);
$titlesList = (new TitleController($config, $logger, $root, $fake))->index(request('/titles'), []);
check('作品列表返回 200 且含作品名', $titlesList->status === 200 && str_contains($titlesList->body, '风起小城（示例作品）'));

// --- P03 搜索 ---
$searchEmpty = (new SearchController($config, $logger, $root, $fake))->index(request('/search'), []);
check('空搜索词不查库且提示输入', $searchEmpty->status === 200 && str_contains($searchEmpty->body, '输入作品名或关键词'));
$searchHit = (new SearchController($config, $logger, $root, $fake))->index(request('/search', ['q' => '车站']), []);
check('搜索命中非剧透帖', $searchHit->status === 200 && str_contains($searchHit->body, '雨后的车站很好看'));
check('搜索结果不含剧透正文', !str_contains($searchHit->body, $secret));
$searchSpoiler = (new SearchController($config, $logger, $root, $fake))->index(request('/search', ['q' => '凶手']), []);
check('按剧透正文里的词搜不到剧透帖', !str_contains($searchSpoiler->body, FakeReader::SPOILER_TITLE) && !str_contains($searchSpoiler->body, $secret));
$searchMiss = (new SearchController($config, $logger, $root, $fake))->index(request('/search', ['q' => '不存在的关键词']), []);
check('搜索无结果时给出空状态', str_contains($searchMiss->body, '没有匹配的作品'));

// --- 分享摘要不泄露 ---
check('剧透帖页面 meta 描述不含正文', !str_contains($thread->body, 'content="' . $secret));

// --- HTTP 层（走 Bootstrap，未配置数据库）---
$notFound = $app->handle(request('/no-such-page'));
check('未知路径返回 404', $notFound->status === 404 && str_contains($notFound->body, '页面不存在'));

$health = $app->handle(request('/health'));
$healthData = json_decode($health->body, true);
check('健康检查返回 200 JSON', $health->status === 200 && is_array($healthData));
check('健康检查标明 UTC', ($healthData['timezone'] ?? '') === 'UTC');
check('健康检查明确报告数据库未配置', ($healthData['database']['status'] ?? '') === 'not_configured');
check('健康检查不泄露 DSN 或账号', !str_contains($health->body, 'DB_PASS') && !str_contains($health->body, 'pdo_mysql'));

check('HTML 转义防 XSS', View::e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;');
$json = Response::json(['ok' => true, 'text' => '中文']);
check('JSON 响应保留中文且类型正确', str_contains($json->body, '中文') && str_contains($json->contentType, 'application/json'));

// --- Logging and database guards ---
$logPath = $root . '/storage/logs/test-redact.log';
$logger2 = new Logger($logPath, true);
$logger2->info('unit.test', ['password' => 'super-secret-value', 'token' => 'abc123', 'note' => 'visible']);
$logContent = (string) file_get_contents($logPath);
check('日志不写入密码', !str_contains($logContent, 'super-secret-value'));
check('日志脱敏标记存在', str_contains($logContent, '[redacted]'));
check('日志 UTC 时间戳', (bool) preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $logContent));
check('数据库配置不完整时明确失败', throwsRuntime(
    static fn () => Database::connect(['host' => '', 'port' => 3306, 'name' => '', 'user' => '', 'pass' => ''])
));

// ============================================================== P04 账号与发帖

/**
 * 每个场景一套全新的会话与服务，避免上一个用例的登录态污染下一个。
 *
 * @return array{services: App\Services, session: App\MemorySession, csrf: App\Csrf, accounts: FakeAccounts, writer: FakeWriter, limiter: FakeLimiter}
 */
function p04Context(?FakeReader $reader = null, ?FakeAccounts $accounts = null, ?FakeWriter $writer = null): array
{
    global $fake;
    $accounts ??= new FakeAccounts();
    $writer ??= new FakeWriter();
    $limiter = new FakeLimiter();
    $session = new App\MemorySession();
    $csrf = new App\Csrf($session);
    $services = new App\Services(
        $session,
        new App\Auth($session, $accounts),
        $csrf,
        $reader ?? $fake,
        $accounts,
        $writer,
        $limiter
    );
    return [
        'services' => $services,
        'session' => $session,
        'csrf' => $csrf,
        'accounts' => $accounts,
        'writer' => $writer,
        'limiter' => $limiter,
    ];
}

/** @param array<string, mixed> $post */
function postRequest(string $path, array $post, string $ip = '127.0.0.1'): Request
{
    return Request::create('POST', $path, [], $post, $ip);
}

// --- 注册 ---
$ctx = p04Context();
$register = (new App\Controllers\AuthController($config, $logger, $root, $fake, $ctx['services']))->register(
    postRequest('/register', [
        'csrf' => $ctx['csrf']->token(),
        'username' => '新观众',
        'email' => 'new@example.com',
        'password' => 'Passw0rd',
        'password_confirm' => 'Passw0rd',
    ]),
    []
);
check('注册成功返回 303 跳首页', $register->status === 303 && ($register->headers['Location'] ?? '') === '/');
check('注册后自动登录', $ctx['session']->get('uid') !== null);
check('注册写入账号且密码为哈希', (static function () use ($ctx): bool {
    $row = $ctx['accounts']->findByIdentity('新观众');
    return $row !== null && $row['password_hash'] !== 'Passw0rd' && password_verify('Passw0rd', (string) $row['password_hash']);
})());
check('登录时更换会话标识', $ctx['session']->regenerationCount() > 0);

$ctx = p04Context();
$weak = (new App\Controllers\AuthController($config, $logger, $root, $fake, $ctx['services']))->register(
    postRequest('/register', [
        'csrf' => $ctx['csrf']->token(),
        'username' => '弱密码用户',
        'email' => 'weak@example.com',
        'password' => 'abc',
        'password_confirm' => 'abc',
    ]),
    []
);
check('弱密码被拒并说明原因', $weak->status === 422 && str_contains($weak->body, '密码至少 8 位'));
check('弱密码未创建账号', $ctx['accounts']->findByIdentity('弱密码用户') === null);

$ctx = p04Context();
$ctx['accounts']->seed('已有用户', 'old@example.com', 'Passw0rd1');
$dup = (new App\Controllers\AuthController($config, $logger, $root, $fake, $ctx['services']))->register(
    postRequest('/register', [
        'csrf' => $ctx['csrf']->token(),
        'username' => '已有用户',
        'email' => 'another@example.com',
        'password' => 'Passw0rd',
        'password_confirm' => 'Passw0rd',
    ]),
    []
);
check('重复用户名被拒', $dup->status === 422 && str_contains($dup->body, '已经被用了'));

$ctx = p04Context();
$noCsrf = (new App\Controllers\AuthController($config, $logger, $root, $fake, $ctx['services']))->register(
    postRequest('/register', [
        'username' => '无令牌用户',
        'email' => 'nocsrf@example.com',
        'password' => 'Passw0rd',
        'password_confirm' => 'Passw0rd',
    ]),
    []
);
check('缺少 CSRF 令牌时拒绝注册', $noCsrf->status === 422 && str_contains($noCsrf->body, '请求已失效'));
check('缺少 CSRF 时没有写入账号', $ctx['accounts']->findByIdentity('无令牌用户') === null);

// --- 登录 ---
$ctx = p04Context();
$ctx['accounts']->seed('已有用户', 'old@example.com', 'Passw0rd1');
$login = (new App\Controllers\AuthController($config, $logger, $root, $fake, $ctx['services']))->login(
    postRequest('/login', ['csrf' => $ctx['csrf']->token(), 'identity' => '已有用户', 'password' => 'Passw0rd1']),
    []
);
check('正确凭据登录成功', $login->status === 303 && $ctx['session']->get('uid') !== null);

$ctx = p04Context();
$ctx['accounts']->seed('已有用户', 'old@example.com', 'Passw0rd1');
$wrong = (new App\Controllers\AuthController($config, $logger, $root, $fake, $ctx['services']))->login(
    postRequest('/login', ['csrf' => $ctx['csrf']->token(), 'identity' => '已有用户', 'password' => 'wrong-pass1']),
    []
);
check('错误密码返回 422', $wrong->status === 422);
check('错误密码不建立会话', $ctx['session']->get('uid') === null);
check('密码错误与账号不存在使用同一提示', str_contains($wrong->body, '用户名或密码不对'));

$ctx = p04Context();
$missing = (new App\Controllers\AuthController($config, $logger, $root, $fake, $ctx['services']))->login(
    postRequest('/login', ['csrf' => $ctx['csrf']->token(), 'identity' => '不存在的人', 'password' => 'Passw0rd1']),
    []
);
check('不存在的账号不暴露差异', $missing->status === 422 && str_contains($missing->body, '用户名或密码不对'));

$ctx = p04Context();
$ctx['accounts']->seed('停用用户', 'disabled@example.com', 'Passw0rd1', 'user', 'disabled');
$disabled = (new App\Controllers\AuthController($config, $logger, $root, $fake, $ctx['services']))->login(
    postRequest('/login', ['csrf' => $ctx['csrf']->token(), 'identity' => '停用用户', 'password' => 'Passw0rd1']),
    []
);
check('停用账号不能登录', $disabled->status === 403 && $ctx['session']->get('uid') === null);

// 第 11 次尝试触发账号维度限流（10 次 / 15 分钟）
$ctx = p04Context();
$ctx['accounts']->seed('已有用户', 'old@example.com', 'Passw0rd1');
$lastStatus = 0;
for ($i = 0; $i < 11; $i++) {
    $attempt = (new App\Controllers\AuthController($config, $logger, $root, $fake, $ctx['services']))->login(
        postRequest('/login', ['csrf' => $ctx['csrf']->token(), 'identity' => '已有用户', 'password' => 'bad-pass' . $i]),
        []
    );
    $lastStatus = $attempt->status;
}
check('连续失败登录触发限流', $lastStatus === 429);

// --- 退出 ---
$ctx = p04Context();
$ctx['accounts']->seed('已有用户', 'old@example.com', 'Passw0rd1');
$ctx['services']->auth->login(1);
$logout = (new App\Controllers\AuthController($config, $logger, $root, $fake, $ctx['services']))->logout(
    postRequest('/logout', ['csrf' => $ctx['csrf']->token()]),
    []
);
check('退出登录清空会话', $logout->status === 303 && $ctx['session']->get('uid') === null);

// --- 发帖 ---
$ctx = p04Context();
$anonymousPublish = (new App\Controllers\PublishController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/publish', ['csrf' => $ctx['csrf']->token(), 'category' => 'movie', 'title' => '标题', 'body' => '正文']),
    []
);
check('未登录发帖跳转登录页', $anonymousPublish->status === 303 && ($anonymousPublish->headers['Location'] ?? '') === '/login');

$ctx = p04Context();
$ctx['accounts']->seed('发帖人', 'poster@example.com', 'Passw0rd1');
$ctx['services']->auth->login(1);
$published = (new App\Controllers\PublishController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/publish', [
        'csrf' => $ctx['csrf']->token(),
        'category' => 'movie',
        'title' => '刚看完，聊聊结尾',
        'body' => '这段是我的观感，不是剧情简介。',
        'is_spoiler' => '1',
    ]),
    []
);
check('登录后发帖成功并跳到帖子页', $published->status === 303 && str_starts_with((string) ($published->headers['Location'] ?? ''), '/thread/'));
check('发帖写入作者与剧透标记', (static function () use ($ctx): bool {
    $row = array_values($ctx['writer']->threads)[0] ?? null;
    return $row !== null && $row['author_id'] === 1 && $row['is_spoiler'] === true && $row['status'] === 'visible';
})());

$ctx = p04Context();
$ctx['accounts']->seed('发帖人', 'poster@example.com', 'Passw0rd1');
$ctx['services']->auth->login(1);
$badCategory = (new App\Controllers\PublishController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/publish', ['csrf' => $ctx['csrf']->token(), 'category' => 'nope', 'title' => '标题', 'body' => '正文']),
    []
);
check('非法分类被拒', $badCategory->status === 422 && str_contains($badCategory->body, '请选择一个分类'));

$ctx = p04Context();
$ctx['accounts']->seed('发帖人', 'poster@example.com', 'Passw0rd1');
$ctx['services']->auth->login(1);
$tooLong = (new App\Controllers\PublishController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/publish', [
        'csrf' => $ctx['csrf']->token(),
        'category' => 'chat',
        'title' => str_repeat('长', 151),
        'body' => '正文',
    ]),
    []
);
check('超长标题被拒', $tooLong->status === 422 && str_contains($tooLong->body, '标题最多 150'));

// 受限账号：内容进入待审
$ctx = p04Context();
$ctx['accounts']->seed('受限用户', 'restricted@example.com', 'Passw0rd1', 'user', 'restricted');
$ctx['services']->auth->login(1);
$pendingPublish = (new App\Controllers\PublishController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/publish', ['csrf' => $ctx['csrf']->token(), 'category' => 'chat', 'title' => '受限账号的帖子', 'body' => '正文']),
    []
);
check('受限账号发帖进入待审', (static function () use ($ctx): bool {
    $row = array_values($ctx['writer']->threads)[0] ?? null;
    return $row !== null && $row['status'] === 'pending';
})());
check('受限账号发帖后提示需审核', str_contains((string) $ctx['session']->get('flash'), '需要管理员看过'));

// --- 评论 ---
$ctx = p04Context();
$ctx['accounts']->seed('评论人', 'commenter@example.com', 'Passw0rd1');
$ctx['services']->auth->login(1);
$dummyThread = $ctx['writer']->createThread(1, 'movie', '可评论的帖子', '正文', null, false, 'visible');
$comment = (new App\Controllers\CommentController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/thread/' . $dummyThread . '/comment', ['csrf' => $ctx['csrf']->token(), 'body' => '我也这么觉得']),
    ['id' => (string) $dummyThread]
);
check('登录后评论成功并回到评论区', $comment->status === 303 && str_contains((string) ($comment->headers['Location'] ?? ''), '#comments'));
check('评论写入正确帖子与作者', (static function () use ($ctx, $dummyThread): bool {
    $row = array_values($ctx['writer']->comments)[0] ?? null;
    return $row !== null && $row['thread_id'] === $dummyThread && $row['author_id'] === 1 && $row['status'] === 'visible';
})());

$ctx = p04Context();
$ctx['accounts']->seed('评论人', 'commenter@example.com', 'Passw0rd1');
$ctx['services']->auth->login(1);
$hiddenThreadComment = (new App\Controllers\CommentController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/thread/2/comment', ['csrf' => $ctx['csrf']->token(), 'body' => '想评论隐藏帖']),
    ['id' => '2']
);
check('不能评论非公开帖子', $hiddenThreadComment->status === 404);
check('非公开帖子没有写入评论', $ctx['writer']->comments === []);

$ctx = p04Context();
$ctx['accounts']->seed('评论人', 'commenter@example.com', 'Passw0rd1');
$ctx['services']->auth->login(1);
$threadIdForCsrf = $ctx['writer']->createThread(1, 'movie', '帖子', '正文', null, false, 'visible');
$csrfComment = (new App\Controllers\CommentController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/thread/' . $threadIdForCsrf . '/comment', ['body' => '没有令牌']),
    ['id' => (string) $threadIdForCsrf]
);
check('评论缺少 CSRF 被拒', $csrfComment->status === 422 && $ctx['writer']->comments === []);

// --- 举报 ---
$ctx = p04Context();
$ctx['accounts']->seed('举报人', 'reporter@example.com', 'Passw0rd1');
$ctx['services']->auth->login(1);
$report = (new App\Controllers\ReportController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/report', [
        'csrf' => $ctx['csrf']->token(),
        'target_type' => 'thread',
        'target_id' => '2',
        'reason' => '这条内容有问题',
    ]),
    []
);
check('举报提交成功并回到原帖', $report->status === 303 && ($report->headers['Location'] ?? '') === '/thread/2');
check('举报写入仓储', count($ctx['writer']->reports) === 1);

$ctx['writer']->createReport(1, 'thread', 2, '重复举报');
$repeat = (new App\Controllers\ReportController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/report', [
        'csrf' => $ctx['csrf']->token(),
        'target_type' => 'thread',
        'target_id' => '2',
        'reason' => '再举报一次同样的问题',
    ]),
    []
);
check('重复举报不新增队列项', $repeat->status === 303 && count($ctx['writer']->reports) === 2);

$ctx = p04Context();
$ctx['accounts']->seed('举报人', 'reporter@example.com', 'Passw0rd1');
$ctx['services']->auth->login(1);
$badReport = (new App\Controllers\ReportController($config, $logger, $root, $fake, $ctx['services']))->create(
    postRequest('/report', ['csrf' => $ctx['csrf']->token(), 'target_type' => 'thread', 'target_id' => '999', 'reason' => '举报原因']),
    []
);
check('举报不存在的帖子返回 404', $badReport->status === 404);

// --- 用户主页 ---
$profilePage = (new App\Controllers\UserController($config, $logger, $root, $fake))->show(request('/user/1'), ['id' => '1']);
check('用户主页返回 200', $profilePage->status === 200 && str_contains($profilePage->body, '示例观众甲'));
check('用户主页不泄露邮箱', !str_contains($profilePage->body, '@example.com'));
$missingProfile = (new App\Controllers\UserController($config, $logger, $root, $fake))->show(request('/user/999'), ['id' => '999']);
check('不存在的用户返回 404', $missingProfile->status === 404);

// ============================================================== P05 审核与举报

$adminCtx = static function () use ($config, $logger, $root, $fake): array {
    $ctx = p04Context($fake);
    $ctx['accounts']->seed('管理员', 'admin@example.com', 'Passw0rd1', 'admin');
    $ctx['accounts']->seed('普通用户', 'user@example.com', 'Passw0rd1');
    return $ctx;
};

$ctx = $adminCtx();
$anonAdmin = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->index(request('/admin'), []);
check('未登录访问管理后台跳转登录', $anonAdmin->status === 303 && ($anonAdmin->headers['Location'] ?? '') === '/login');

$ctx = $adminCtx();
$ctx['services']->auth->login(2);
$normalAdmin = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->index(request('/admin'), []);
check('普通用户访问管理后台返回 403', $normalAdmin->status === 403);

$ctx = $adminCtx();
$ctx['services']->auth->login(1);
$adminPage = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->index(request('/admin'), []);
check('管理员可打开管理后台', $adminPage->status === 200 && str_contains($adminPage->body, '举报队列'));
check('管理后台含操作记录区块', str_contains($adminPage->body, '操作记录'));

$ctx = $adminCtx();
$ctx['services']->auth->login(1);
$targetThread = $ctx['writer']->createThread(2, 'movie', '待处理帖子', '正文', null, false, 'visible');
$hide = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->threadStatus(
    postRequest('/admin/thread/' . $targetThread, ['csrf' => $ctx['csrf']->token(), 'status' => 'hidden']),
    ['id' => (string) $targetThread]
);
check('管理员隐藏帖子成功', $hide->status === 303 && $ctx['writer']->threads[$targetThread]['status'] === 'hidden');
check('隐藏操作写入审计日志', (static function () use ($ctx): bool {
    $last = $ctx['writer']->audit[count($ctx['writer']->audit) - 1] ?? [];
    return ($last['action'] ?? '') === 'thread.hidden' && ($last['target_id'] ?? 0) > 0;
})());

$ctx = $adminCtx();
$ctx['services']->auth->login(1);
$badStatus = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->threadStatus(
    postRequest('/admin/thread/1', ['csrf' => $ctx['csrf']->token(), 'status' => 'root']),
    ['id' => '1']
);
check('非法处置状态被拒且不写库', $badStatus->status === 303 && $ctx['writer']->audit === []);

$ctx = $adminCtx();
$ctx['services']->auth->login(1);
$selfLock = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->userStatus(
    postRequest('/admin/user/1', ['csrf' => $ctx['csrf']->token(), 'status' => 'disabled']),
    ['id' => '1']
);
check('管理员不能停用自己', $selfLock->status === 303 && $ctx['accounts']->row(1)['status'] === 'active');
check('拒绝自我停用时给出提示', str_contains((string) $ctx['session']->get('flash'), '不能限制或停用'));

$ctx = $adminCtx();
$ctx['services']->auth->login(1);
$restrictUser = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->userStatus(
    postRequest('/admin/user/2', ['csrf' => $ctx['csrf']->token(), 'status' => 'restricted']),
    ['id' => '2']
);
check('管理员可限制账号', $restrictUser->status === 303 && $ctx['accounts']->row(2)['status'] === 'restricted');
check('账号处置写入审计日志', (static function () use ($ctx): bool {
    $last = $ctx['writer']->audit[count($ctx['writer']->audit) - 1] ?? [];
    return ($last['action'] ?? '') === 'user.restricted';
})());

$ctx = $adminCtx();
$ctx['services']->auth->login(1);
$ctx['writer']->createReport(2, 'thread', 2, '内容不合适');
$reportId = (int) array_key_first($ctx['writer']->reports);
$dismiss = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->report(
    postRequest('/admin/report/' . $reportId, ['csrf' => $ctx['csrf']->token(), 'action' => 'dismiss']),
    ['id' => (string) $reportId]
);
check('驳回举报后状态为 dismissed', $dismiss->status === 303 && $ctx['writer']->reports[$reportId]['status'] === 'dismissed');

$ctx = $adminCtx();
$ctx['services']->auth->login(1);
$reportedThread = $ctx['writer']->createThread(2, 'movie', '被举报的帖子', '正文', null, false, 'visible');
$ctx['writer']->createReport(2, 'thread', $reportedThread, '内容不合适');
$rid = (int) array_key_first($ctx['writer']->reports);
$hideTarget = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->report(
    postRequest('/admin/report/' . $rid, ['csrf' => $ctx['csrf']->token(), 'action' => 'hide_target']),
    ['id' => (string) $rid]
);
check('处理举报时隐藏被举报内容', $hideTarget->status === 303
    && $ctx['writer']->threads[$reportedThread]['status'] === 'hidden'
    && $ctx['writer']->reports[$rid]['status'] === 'resolved');

$ctx = $adminCtx();
$ctx['services']->auth->login(1);
$adminCsrf = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->threadStatus(
    postRequest('/admin/thread/1', ['status' => 'hidden']),
    ['id' => '1']
);
check('管理操作缺少 CSRF 被拒', $adminCsrf->status === 422);

// 评论举报的「查看内容」必须回到所属帖子，而不是把评论 id 当帖子 id
$ctx = $adminCtx();
$ctx['services']->auth->login(1);
$commentedThread = $ctx['writer']->createThread(2, 'movie', '带评论的帖子', '正文', null, false, 'visible');
$commentId = $ctx['writer']->createComment($commentedThread, 2, '被举报的评论', false, 'visible');
$ctx['writer']->createReport(2, 'comment', $commentId, '评论不合适');
$adminWithCommentReport = (new App\Controllers\AdminController($config, $logger, $root, $fake, $ctx['services']))->index(request('/admin'), []);
check(
    '评论举报链接指向所属帖子',
    str_contains($adminWithCommentReport->body, '/thread/' . $commentedThread . '#comments')
);

echo PHP_EOL . 'RESULT: ' . $passed . ' passed, ' . $failed . ' failed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
