<?php

declare(strict_types=1);

namespace LiebAbstractSite\Container;

use DI\Container;
use Slim\Views\Twig;
use Doctrine\DBAL\Connection;
use FastRoute\RouteCollector;
use LiebAbstractSite\Controller;
use LiebAbstractSite\Model\Platform;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use LiebAbstractSite\Middleware\LogVisitMiddleware;

final class ContainerRegister
{
    public static function register(Container $container): void
    {
        self::registerSimple(
            $container,
            LogVisitMiddleware::class,
            Connection::class,
            Platform::class,
        );

        self::registerControllers($container);
    }

    private static function registerControllers(Container $container): void
    {
        self::registerSimple(
            $container,
            Controller\SitemapController::class,
            RouteCollector::class,
            StreamFactoryInterface::class,
            Connection::class,
            Platform::class,
        );
        self::registerSimple(
            $container,
            Controller\SimplePageController::class,
            Twig::class,
        );
        self::registerSimple(
            $container,
            Controller\ContactController::class,
            Connection::class,
            Twig::class,
            Platform::class,
        );
        self::registerSimple(
            $container,
            Controller\Book\BuyController::class,
            ResponseFactoryInterface::class,
            Connection::class,
        );
        self::registerSimple(
            $container,
            Controller\Book\DetailController::class,
            Connection::class,
            Twig::class,
            Platform::class,
        );
        self::registerSimple(
            $container,
            Controller\Book\PublishedController::class,
            Connection::class,
            Twig::class,
            Platform::class,
        );
    }

    /**
     * @param class-string $class
     */
    private static function registerSimple(Container $container, string $class, string ...$params): void
    {
        $container->set(
            $class,
            static fn() => new $class(
                ...array_map(
                    static fn(string $param): mixed => $container->get($param),
                    $params,
                ),
            ),
        );
    }
}
