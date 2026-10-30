<?php

declare(strict_types=1);

namespace Survos\DuckDbApiBundle;

use ApiPlatform\State\Pagination\PaginatorInterface;

/** @implements \IteratorAggregate<int,object> */
final readonly class Page implements \IteratorAggregate, PaginatorInterface
{
    public function __construct(private array $items, private int $page, private int $limit, private int $total)
    {
    }

    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getCurrentPage(): float
    {
        return $this->page;
    }

    public function getItemsPerPage(): float
    {
        return $this->limit;
    }

    public function getTotalItems(): float
    {
        return $this->total;
    }

    public function getLastPage(): float
    {
        return max(1, ceil($this->total / $this->limit));
    }
}
