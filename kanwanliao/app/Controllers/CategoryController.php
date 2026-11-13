<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Category;
use App\Request;
use App\Response;
use Throwable;

final class CategoryController extends ForumController
{
    private const PAGE_SIZE = 10;

    /** @param array<string, string> $params */
    public function index(Request $request, array $params): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if (!Category::exists($slug)) {
            return $this->notFound('分类');
        }
        if ($this->reader === null) {
            return $this->unavailable();
        }

        $page = $this->pageFrom($request);
        try {
            $total = $this->reader->countThreads($slug, '');
            $pager = $this->pagerFor($page, self::PAGE_SIZE, $total);
            $rows = $this->reader->threads($slug, '', $pager->page, $pager->pageSize);
        } catch (Throwable $error) {
            $this->logger->error('category.read', ['type' => get_class($error)]);
            return $this->unavailable();
        }

        $name = Category::name($slug);

        return $this->render('home', [
            'pageTitle' => $name . ' · 分类',
            'navKey' => 'category',
            'threads' => $this->decorateThreads($rows),
            'pager' => $pager,
            'basePath' => '/category/' . $slug,
            'categories' => Category::all(),
            'activeCategory' => $slug,
            'heading' => $name,
            'subheading' => '只看「' . $name . '」这一类讨论。',
        ]);
    }
}
