<?php

declare(strict_types=1);

namespace LiebAbstractSite\Controller\Helper;

use Psr\Http\Message\ServerRequestInterface;

// @phpstan-ignore trait.unused
trait PaginationHelper
{
    private function getPaginationPage(ServerRequestInterface $request): ?int
    {
        $queryParams = $request->getQueryParams();

        if (isset($queryParams['page'])) {
            if (is_string($queryParams['page']) && ctype_digit($queryParams['page']) && $queryParams['page'] > 1) {
                return (int)$queryParams['page'];
            }
        } else {
            return 1;
        }

        return null;
    }
}
