<?php

declare(strict_types=1);

namespace LiebAbstractSite\Controller\Book;

use Slim\Views\Twig;
use Slim\Psr7\Request;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Error\RuntimeError;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Connection;
use LiebAbstractSite\Model\Platform;
use LiebAbstractSite\Model\Pagination;
use Psr\Http\Message\ResponseInterface;
use LiebAbstractSite\Model\Exception\PageOutBounds;
use LiebAbstractSite\Database\Querier\Book\BookQuerier;
use LiebAbstractSite\Controller\Helper\PaginationHelper;

final class PublishedController
{
    use PaginationHelper;

    private const int PER_PAGE = 27;

    /**
     * @psalm-pure
     */
    public function __construct(
        private readonly Connection $db,
        private readonly Twig $view,
        private readonly Platform $platform,
    ) {}

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function __invoke(Request $request, ResponseInterface $response): ResponseInterface
    {
        $page = $this->getPaginationPage($request);

        if ($page === null) {
            return $response
                ->withStatus(302)
                ->withHeader('Location', $request->getUri()->getPath());
        }

        $querier = (new BookQuerier($this->db, $this->platform))
            ->paginate($page, self::PER_PAGE)
            ->addOrderByPublication();

        $totalRows = $querier->count();

        try {
            $pagination = new Pagination(self::PER_PAGE, $totalRows, $page);
        } catch (PageOutBounds) {
            return $response
                ->withStatus(302)
                ->withHeader('Location', $request->getUri()->getPath());
        }

        $books = $querier->fetchAll();

        return $this->view->render(
            $response,
            "/page/book/published.twig",
            [
                'pagination' => $pagination,
                'books' => $books,
            ],
        );
    }
}
