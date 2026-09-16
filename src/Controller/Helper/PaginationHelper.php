<?php

declare(strict_types=1);

namespace LiebAbstractSite\Controller\Helper;

use Psr\Http\Message\ServerRequestInterface;

// @phpstan-ignore trait.unused
trait PaginationHelper
{
    private function getPaginationPage(ServerRequestInterface $request): ?int
    {
        $page = $request->getQueryParams()['page'] ?? null;

        if ($page === null) {
            return 1;
        }

        if (is_string($page) && ctype_digit($page) && $page > 1) {
            return (int) $page;
        }

        return null;
    }
}
