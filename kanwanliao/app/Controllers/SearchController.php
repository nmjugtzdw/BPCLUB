<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Response;
use Throwable;

final class SearchController extends ForumController
{
    private const PAGE_SIZE = 10;

    /** @param array<string, string> $params */
    public function index(Request $request, array $params): Response
    {
        $query = $this->stringQuery($request, 'q', 60);
        $page = $this->pageFrom($request);

        if ($query === '') {
            return $this->render('search', [
                'pageTitle' => '搜索',
                'metaDescription' => '搜索作品和讨论。',
                'navKey' => 'search',
                'query' => '',
                'threads' => [],
                'titles' => [],
                'pager' => $this->pagerFor(1, self::PAGE_SIZE, 0),
                'searched' => false,
            ]);
        }

        if ($this->reader === null) {
            return $this->unavailable();
        }

        try {
            $result = $this->reader->search($query, $page, self::PAGE_SIZE);
        } catch (Throwable $error) {
            $this->logger->error('search.read', ['type' => get_class($error)]);
            return $this->unavailable();
        }

        $pager = $this->pagerFor($page, self::PAGE_SIZE, $result['threadTotal']);

        return $this->render('search', [
            'pageTitle' => '搜索「' . $query . '」',
            'metaDescription' => '搜索作品和讨论。',
            'navKey' => 'search',
            'query' => $query,
            'threads' => $this->decorateThreads($result['threads']),
            'titles' => $result['titles'],
            'pager' => $pager,
            'searched' => true,
        ]);
    }
}
