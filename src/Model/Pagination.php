<?php

declare(strict_types=1);

namespace LiebAbstractSite\Model;

use LiebAbstractSite\Model\Exception\PageOutBounds;

/**
 * @psalm-immutable
 */
final class Pagination
{
    // phpcs:disable
    public int $maxPage {
        get => max(1, (int) ceil($this->total / $this->perPage));
    }

    public int $offset {
        get => ($this->page - 1) * $this->perPage;
    }

    public bool $hasPrevious {
        get => $this->page > 1;
    }

    public bool $hasNext {
        get => $this->page < $this->maxPage;
    }

    /** @var array<int> */
    public array $range {
        get => range(
            max(1, $this->page - $this->padding - max(0, 3 + $this->page - $this->maxPage)),
            min($this->maxPage, $this->page + $this->padding + max(0, (4 - $this->page)))
        );
    }
    // phpcs:enable

    /**
     * @throws PageOutBounds
     *
     * @psalm-pure
     */
    public function __construct(
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $page,
        public readonly int $padding = 3,
    ) {
        if ($this->page > $this->maxPage) {
            throw new PageOutBounds($this->page, $this->maxPage);
        }
    }
}
