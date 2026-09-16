<?php

declare(strict_types=1);

namespace LiebAbstractSite\Controller;

use Slim\Views\Twig;
use Slim\Psr7\Request;
use Twig\Error\SyntaxError;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Psr\Http\Message\ResponseInterface;

final class SimplePageController
{
    /**
     * @psalm-pure
     */
    public function __construct(
        private readonly Twig $view,
    ) {}

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(Request $request, ResponseInterface $response): ResponseInterface
    {
        $path = rtrim($request->getUri()->getPath(), '/');

        if ($path === '') {
            $path = '/index';
        }

        return $this->view->render($response, "/page{$path}.twig");
    }
}
