<?php

declare(strict_types=1);

namespace LiebAbstractSiteTest\Model;

use LiebAbstractSite\Model\Pagination;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Pagination::class)]
class PaginationTest extends TestCase
{
    public function testGetters(): void
    {
        $pagination = new Pagination(
            25,
            200,
            5,
            3
        );

        self::assertSame(8, $pagination->maxPage);
        self::assertSame(100, $pagination->offset);
        self::assertTrue($pagination->hasPrevious);
        self::assertTrue($pagination->hasNext);

        self::assertSame(
            [
                2,
                3,
                4,
                5,
                6,
                7,
                8,
            ],
            $pagination->range,
        );
    }
}
