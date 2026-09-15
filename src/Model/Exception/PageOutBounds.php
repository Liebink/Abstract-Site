<?php

declare(strict_types=1);

namespace LesAbstractSite\Model\Exception;

use Exception;
use LesAbstractSite\Exception\AbstractSiteException;

/**
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class PageOutBounds extends Exception implements AbstractSiteException
{
    /**
     * @psalm-pure
     */
    public function __construct(public readonly int $page, public readonly int $maxPage)
    {
        parent::__construct("Page {$page} out of bounds {$maxPage} is max");
    }
}
