<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Category;
use App\Request;
use App\Response;
use Throwable;

final class HomeController extends ForumController
{
    private const PAGE_SIZE = 10;

    /** @param array<string, string> $params */
    public function index(Request $request, array $params): Response
    {
        if ($this->reader === null) {
            return $this->unavailable();
        }

        $page = $this->pageFrom($request);
        try {
            $total = $this->reader->countThreads(null, '');
            $pager = $this->pagerFor($page, self::PAGE_SIZE, $total);
            $rows = $this->reader->threads(null, '', $pager->page, $pager->pageSize);
        } catch (Throwable $error) {
            $this->logger->error('home.read', ['type' => get_class($error)]);
            return $this->unavailable();
        }

        return $this->render('home', [
            'pageTitle' => '最近大家在聊',
            'navKey' => 'home',
            'threads' => $this->decorateThreads($rows),
            'pager' => $pager,
            'basePath' => '/',
            'categories' => Category::all(),
            'activeCategory' => null,
        ]);
    }
}
