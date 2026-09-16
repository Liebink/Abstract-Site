<?php

declare(strict_types=1);

namespace LiebAbstractSiteTest\Controller\Helper;

use Psr\Http\Message\ServerRequestInterface;
use LiebAbstractSite\Controller\Helper\PaginationHelper;
use PHPUnit\Framework\TestCase;

class PaginationHelperTest extends TestCase
{
    public function testNoPageSet(): void
    {
        $class = new class {
            use PaginationHelper {
                getPaginationPage as public;
            }
        };

        $request = $this->createMock(ServerRequestInterface::class);

        self::assertSame(1, $class->getPaginationPage($request));
    }

    public function testPageSet(): void
    {
        $class = new class {
            use PaginationHelper {
                getPaginationPage as public;
            }
        };

        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->method('getQueryParams')
            ->willReturn(['page' => '2']);

        self::assertSame(2, $class->getPaginationPage($request));
    }

    public function testPageSetInvalid(): void
    {
        $class = new class {
            use PaginationHelper {
                getPaginationPage as public;
            }
        };

        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->method('getQueryParams')
            ->willReturn(['page' => '1']);

        self::assertNull($class->getPaginationPage($request));
    }
}
