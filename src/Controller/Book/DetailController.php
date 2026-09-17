<?php

declare(strict_types=1);

namespace LiebAbstractSite\Controller\Book;

use JsonException;
use Slim\Views\Twig;
use RuntimeException;
use Slim\Psr7\Request;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Error\RuntimeError;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Connection;
use LiebAbstractSite\Model\Platform;
use Psr\Http\Message\ResponseInterface;
use LiebAbstractSite\Database\Querier\Book\BookQuerier;
use LiebAbstractSite\Database\Querier\Contributor\ContributorQuerier;

final class DetailController
{
    private const string SELECT_TOPIC_ALTERNATIVES_QUERY = <<<'SQL'
SELECT reference_id

FROM product_topic_link ptl

WHERE
    ptl.topic IN (%s)
    AND
    ptl.reference_id != :ignore_id
    AND
    state = 'active'
    AND
    ptl.reference_type = 'lieb.product.book'
    AND
    EXISTS (
        SELECT *
        FROM product_book_listing pbl
        INNER JOIN product_book pb ON pb.id = pbl.book
        WHERE
            pbl.book = ptl.reference_id
            AND
            pbl.state = 'active'
            AND
            pbl.platform = :platform
            AND
            pb.publication_status = 'active'
    )

GROUP BY reference_id

ORDER BY count(*) desc

LIMIT 3
SQL;

    /**
     * @psalm-pure
     */
    public function __construct(
        private readonly Connection $db,
        private readonly Twig $view,
        private readonly Platform $platform,
    ) {
    }

    /**
     * @param array<mixed> $args
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     * @throws JsonException
     */
    public function __invoke(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!is_string($args['gtin'])) {
            throw new RuntimeException('Invalid GTIN');
        }

        $book = (new BookQuerier($this->db, $this->platform))
            ->andWithGtin($args['gtin'])
            ->limit(1)
            ->fetch();

        if (!is_array($book)) {
            return $this
                ->view
                ->render(
                    $response,
                    "/page/book/detail/not-found.twig",
                    ['gtin' => $args['gtin']],
                )
                ->withStatus(404);
        }

        if (!isset($book['topics']) || !is_array($book['topics']) || count($book['topics']) > 0) {
            $others = [
                'books' => $this->getTopicsAlternatives($book),
                'mode' => 'alternatives',
            ];
        } else {
            $others = null;
        }

        assert(is_string($book['id']));

        $authors = (new ContributorQuerier($this->db, $this->platform))
            ->andWithBook($book['id'], 'author')
            ->fetchAll();

        return $this->view->render(
            $response,
            "/page/book/detail/found.twig",
            [
                'book' => $book,
                'authors' => $authors,
                'others' => $others,
            ],
        );
    }

    /**
     * @param array<mixed> $book
     *
     * @return array<mixed>
     *
     * @throws Exception
     */
    private function getTopicsAlternatives(array $book): array
    {
        $parameters = [
            'platform' => $this->platform->value,
            'ignore_id' => $book['id'],
        ];
        $where = [];

        if (isset($book['topics']) && is_array($book['topics'])) {
            $position = 1;

            foreach ($book['topics'] as $topic) {
                assert(is_array($topic));
                assert(is_string($topic['id']));

                $parameters["topic_id_{$position}"] = $topic['id'];
                $where[] = ":topic_id_{$position}";

                $position += 1;
            }
        }

        $query = sprintf(
            self::SELECT_TOPIC_ALTERNATIVES_QUERY,
            implode(', ', $where),
        );

        $results = $this->db->fetchAllAssociative($query, $parameters);

        return (new BookQuerier($this->db, $this->platform))
            ->andWithIds(
                array_map(
                    static function (array $result): string {
                        assert(is_string($result['reference_id']));

                        return $result['reference_id'];
                    },
                    $results,
                ),
            )
            ->fetchAll();
    }
}
