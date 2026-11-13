<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Response;
use Throwable;

final class TitleController extends ForumController
{
    private const PAGE_SIZE = 10;

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $id = $this->idFrom($params);
        if ($id === null) {
            return $this->notFound('作品');
        }
        if ($this->reader === null) {
            return $this->unavailable();
        }

        try {
            $title = $this->reader->title($id);
            if ($title === null) {
                return $this->notFound('作品');
            }
            $rows = $this->reader->threadsForTitle($id, self::PAGE_SIZE);
        } catch (Throwable $error) {
            $this->logger->error('title.read', ['type' => get_class($error), 'title' => $id]);
            return $this->unavailable();
        }

        return $this->render('title', [
            'pageTitle' => (string) $title['name'],
            'metaDescription' => '与《' . (string) $title['name'] . '》相关的讨论。',
            'navKey' => 'title',
            'title' => $title,
            'threads' => $this->decorateThreads($rows),
        ]);
    }

    /** 作品列表：同名作品按年份与类型并列展示，不做合并。 */
    public function index(Request $request, array $params): Response
    {
        if ($this->reader === null) {
            return $this->unavailable();
        }

        $query = $this->stringQuery($request, 'q');
        $page = $this->pageFrom($request);

        try {
            $total = $this->reader->countTitles($query);
            $pager = $this->pagerFor($page, 20, $total);
            $rows = $this->reader->titles($query, $pager->page, $pager->pageSize);
        } catch (Throwable $error) {
            $this->logger->error('titles.read', ['type' => get_class($error)]);
            return $this->unavailable();
        }

        return $this->render('titles', [
            'pageTitle' => '作品',
            'navKey' => 'titles',
            'titles' => $rows,
            'pager' => $pager,
            'query' => $query,
        ]);
    }

    /** @param array<string, string> $params */
    private function idFrom(array $params): ?int
    {
        $raw = (string) ($params['id'] ?? '');
        if (preg_match('/^[1-9][0-9]{0,9}$/', $raw) !== 1) {
            return null;
        }
        return (int) $raw;
    }
}
