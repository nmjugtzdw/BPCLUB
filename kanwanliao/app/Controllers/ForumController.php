<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Category;
use App\Config;
use App\Csrf;
use App\ForumReader;
use App\Logger;
use App\Pager;
use App\ReaderUnavailable;
use App\Request;
use App\Response;
use App\Services;
use App\Spoiler;
use App\View;
use Throwable;

/**
 * Shared plumbing for every page.
 *
 * A read page needs the database. When it is missing or unreachable the page
 * says so with 503 instead of rendering an empty forum that looks like a real,
 * quiet community — an empty list would be a false statement about the site.
 */
abstract class ForumController
{
    protected readonly Config $config;

    protected readonly Logger $logger;

    protected readonly string $root;

    protected readonly ?ForumReader $reader;

    protected readonly ?Services $services;

    public function __construct(
        Config $config,
        Logger $logger,
        string $root,
        ?ForumReader $reader = null,
        ?Services $services = null
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->root = $root;
        $this->services = $services;
        // Bootstrap passes the same reader twice; tests may pass only the
        // reader, so fall back to the service container when it is present.
        $this->reader = $reader ?? $services?->reader;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data, int $status = 200): Response
    {
        foreach ($this->commonData() as $key => $value) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }
        return Response::html(View::render($this->root, $view, $data), $status);
    }

    protected function renderStatus(int $code, string $title, string $message): Response
    {
        return Response::html(
            View::render($this->root, 'status', [
                'code' => $code,
                'title' => $title,
                'message' => $message,
                'pageTitle' => $title,
            ] + $this->commonData()),
            $code
        );
    }

    /** @return array<string, mixed> */
    protected function commonData(): array
    {
        $user = $this->services?->auth->user();
        return [
            'searchQuery' => '',
            'navKey' => '',
            'authUser' => $user,
            'isAdmin' => $this->services?->auth->isAdmin() ?? false,
            'csrfToken' => $this->services?->csrf->token() ?? '',
            'flash' => $this->takeFlash(),
        ];
    }

    protected function notFound(string $what = '页面'): Response
    {
        return $this->renderStatus(404, $what . '不存在', '没有找到这个' . $what . '，它可能被删除了，或者链接不对。');
    }

    protected function forbidden(): Response
    {
        return $this->renderStatus(403, '没有权限', '这个操作需要对应权限，当前账号不能进行。');
    }

    /**
     * @throws ReaderUnavailable when the read store is not reachable
     */
    protected function requireReader(): ForumReader
    {
        if ($this->reader === null) {
            throw new ReaderUnavailable();
        }
        return $this->reader;
    }

    protected function unavailable(): Response
    {
        return $this->renderStatus(503, '内容暂时无法显示', '讨论内容暂时读不出来，请稍后再试。');
    }

    protected function pageFrom(Request $request): int
    {
        $raw = $request->query['page'] ?? '1';
        if (!is_string($raw) || preg_match('/^\d{1,6}$/', $raw) !== 1) {
            return 1;
        }
        return max(1, (int) $raw);
    }

    protected function stringQuery(Request $request, string $key, int $maxLength = 60): string
    {
        $raw = $request->query[$key] ?? '';
        if (!is_string($raw)) {
            return '';
        }
        $value = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        return mb_substr($value, 0, $maxLength);
    }

    protected function pagerFor(int $page, int $pageSize, int $total): Pager
    {
        return new Pager($page, $pageSize, $total);
    }

    /**
     * Add the display-only fields every list template needs. Kept in one place
     * so a list view can never hand a raw spoiler body to the excerpt rule.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function decorateThreads(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $isSpoiler = (int) ($row['is_spoiler'] ?? 0) === 1;
            $row['category_name'] = Category::name((string) ($row['category'] ?? ''));
            $row['excerpt'] = Spoiler::summary((string) ($row['public_body'] ?? ''), $isSpoiler);
            $row['is_spoiler'] = $isSpoiler;
            $out[] = $row;
        }
        return $out;
    }

    // ------------------------------------------------------------ auth & csrf

    /** @throws ReaderUnavailable when the request has no service container */
    protected function services(): Services
    {
        if ($this->services === null) {
            throw new ReaderUnavailable();
        }
        return $this->services;
    }

    protected function auth(): ?Auth
    {
        return $this->services?->auth;
    }

    protected function csrf(): ?Csrf
    {
        return $this->services?->csrf;
    }

    protected function loggedIn(): bool
    {
        return $this->auth()?->check() ?? false;
    }

    protected function isAdmin(): bool
    {
        return $this->auth()?->isAdmin() ?? false;
    }

    /** 未登录时跳去登录页；已登录返回 null。 */
    protected function requireLogin(): ?Response
    {
        if ($this->loggedIn()) {
            return null;
        }
        return Response::redirect('/login');
    }

    protected function requireAdmin(): ?Response
    {
        if (!$this->loggedIn()) {
            return Response::redirect('/login');
        }
        if (!$this->isAdmin()) {
            return $this->forbidden();
        }
        return null;
    }

    /** CSRF 校验失败：422，不执行任何写操作。 */
    protected function rejectCsrf(): Response
    {
        return $this->renderStatus(422, '请求已失效', '页面停留太久或来源不可信，请回到上一页刷新后重新提交。');
    }

    protected function flash(string $type, string $message): void
    {
        $session = $this->services?->session;
        if ($session === null) {
            return;
        }
        $session->set('flash', json_encode(['type' => $type, 'message' => $message], JSON_UNESCAPED_UNICODE) ?: '');
    }

    /** @return array{type: string, message: string}|null */
    protected function takeFlash(): ?array
    {
        $session = $this->services?->session;
        if ($session === null) {
            return null;
        }
        $raw = $session->get('flash');
        if ($raw === null || $raw === '') {
            return null;
        }
        $session->remove('flash');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['message']) || !is_string($decoded['message'])) {
            return null;
        }
        $type = in_array(($decoded['type'] ?? ''), ['ok', 'error'], true) ? (string) $decoded['type'] : 'ok';
        return ['type' => $type, 'message' => $decoded['message']];
    }

    /** 限流：超限返回 null 表示放行，返回 Response 表示拒绝。 */
    protected function rateLimit(Request $request, string $bucket, string $key, int $limit, int $window): ?Response
    {
        $limiter = $this->services?->limiter;
        if ($limiter === null) {
            // No limiter configured (e.g. database down): authentication and
            // writing are already unavailable, so do not double-fail here.
            return null;
        }
        if ($limiter->attempt($bucket, $request->ip . '|' . $key, $limit, $window)) {
            return null;
        }
        $this->logger->error('rate_limited', ['bucket' => $bucket]);
        return $this->renderStatus(429, '操作太频繁', '请稍等一会儿再试。');
    }

    protected function idFromParam(array $params, string $key = 'id'): ?int
    {
        $raw = (string) ($params[$key] ?? '');
        if (preg_match('/^[1-9][0-9]{0,9}$/', $raw) !== 1) {
            return null;
        }
        return (int) $raw;
    }

    protected function logError(string $event, Throwable $error, array $context = []): void
    {
        $this->logger->error($event, $context + ['type' => get_class($error)]);
    }
}
