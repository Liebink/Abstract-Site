<?php

declare(strict_types=1);

namespace LesAbstractSite\Controller;

use Slim\Psr7\Request;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Connection;
use LesAbstractSite\Model\Platform;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Slim\Interfaces\RouteCollectorInterface;

final class SitemapController
{
    private const string SELECT_BOOK_QUERY = <<<'SQL'
SELECT pbr.value

FROM product_book_listing pbl
INNER JOIN product_book pb ON pb.id = pbl.book
INNER JOIN product_book_reference pbr 
    ON 
        pbr.book = pbl.book
        AND
        pbr.scheme = 'gtin-13'
        AND
        pbr.sub_scheme = 'none'

WHERE
    pbl.platform = :platform
    AND
    pbl.state = 'active'
    AND
    pb.publication_status = 'active'
    AND
    pb.price_lowest_ex_shipping IS NOT NULL
    AND
    EXISTS (
        SELECT *
        FROM product_book_resource pbr
        WHERE
            pbr.book = pb.id 
            AND 
            pbr.role = 'coverFront' 
            AND 
            sequence_number = 0
    )
SQL;

    /**
     * @psalm-pure
     */
    public function __construct(
        private readonly RouteCollectorInterface $routeCollector,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly Connection $db,
        private readonly Platform $platform,
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(Request $request, ResponseInterface $response): ResponseInterface
    {
        $routes = $this->routeCollector->getRoutes();

        $urls = [];

        foreach ($routes as $route) {
            if (str_contains($route->getPattern(), '{')) {
                continue;
            }

            if ($route->getPattern() === '/sitemap.txt') {
                continue;
            }

            $urls[] = sprintf(
                "https://%s%s",
                $this->platform->getBaseUrl(),
                $route->getPattern(),
            );
        }

        $books = $this
            ->db
            ->fetchAllAssociative(
                self::SELECT_BOOK_QUERY,
                ['platform' => $this->platform->value],
            );

        foreach ($books as $book) {
            assert(is_string($book['value']));

            $urls[] = sprintf(
                "https://%s/boek/%s",
                $this->platform->getBaseUrl(),
                $book['value'],
            );
        }

        $urlText = implode("\n", array_unique($urls));

        $stream = $this
            ->streamFactory
            ->createStream($urlText);

        return $response
            ->withBody($stream)
            ->withHeader('Content-Type', 'text/plain');
    }
}
