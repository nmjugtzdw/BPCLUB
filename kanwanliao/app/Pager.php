<?php

declare(strict_types=1);

namespace App;

/**
 * Immutable pagination value object.
 *
 * The page number is clamped to the real page range so a hand-edited
 * "?page=999999" renders the last page instead of an empty list, and a
 * negative or non-numeric value renders the first one.
 */
final class Pager
{
    public readonly int $pageSize;
    public readonly int $total;
    public readonly int $pages;
    public readonly int $page;

    public function __construct(int $page, int $pageSize, int $total)
    {
        $this->pageSize = max(1, $pageSize);
        $this->total = max(0, $total);
        $this->pages = max(1, (int) ceil($this->total / $this->pageSize));
        $this->page = min(max(1, $page), $this->pages);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }

    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages;
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }
}
