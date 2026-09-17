<?php

declare(strict_types=1);

namespace LiebAbstractSite\Controller\Book;

use RuntimeException;
use Slim\Psr7\Request;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final class BuyController
{
    private const string SELECT_QUERY = <<<'SQL'
SELECT pbo.buy_link

FROM product_book_reference pbr
INNER JOIN product_book_offer pbo ON pbo.book = pbr.book
INNER JOIN product_supplier ps ON ps.id = pbo.supplier_id

WHERE
    pbr.scheme = 'gtin-13'
    AND
    pbr.sub_scheme = 'none'
    AND
    pbr.value = :gtin
    AND
    ps.slug = :slug
    AND
    pbo.state = 'available'
SQL;

    /**
     * @psalm-pure
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly Connection $db,
    ) {}

    /**
     * @param array<mixed> $args
     *
     * @throws Exception
     */
    public function __invoke(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!is_string($args['gtin'])) {
            throw new RuntimeException();
        }

        $result = $this
            ->db
            ->fetchOne(
                self::SELECT_QUERY,
                [
                    'gtin' => $args['gtin'],
                    'slug' => $args['via'],
                ],
            );

        if (!is_string($result)) {
            return $this
                ->responseFactory
                ->createResponse(302)
                ->withHeader('Location', "/boek/{$args['gtin']}");
        }

        return $this
            ->responseFactory
            ->createResponse(302)
            ->withHeader('Location', $result);
    }
}
