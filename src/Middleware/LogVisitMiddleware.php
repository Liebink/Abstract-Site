<?php

declare(strict_types=1);

namespace LiebAbstractSite\Middleware;

use Override;
use Throwable;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Connection;
use LiebAbstractSite\Model\Platform;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use LesDatabase\Query\Builder\Applier\Values\InsertValuesApplier;

final class LogVisitMiddleware implements MiddlewareInterface
{
    private ?Throwable $exception = null;

    /**
     * @psalm-pure
     */
    public function __construct(
        private readonly Connection $db,
        private readonly Platform $platform,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = (int)round(microtime(true));
        $response = $handler->handle($request);
        $finish = (int)round(microtime(true));

        $this->insert($request, $response, $start, $finish);

        return $response;
    }

    /**
     * @throws Exception
     */
    private function insert(ServerRequestInterface $request, ResponseInterface $response, int $start, int $finish): void
    {
        $serverParams = $request->getServerParams();
        $queryParams = $request->getQueryParams();

        InsertValuesApplier
            ::forValues(
                [
                    'platform' => $this->platform,
                    'page' => substr($request->getUri()->getPath(), 0, 255),
                    'method' => substr($request->getMethod(), 0, 10),
                    'visited_on' => round(microtime(true) * 1_000.0),
                    'http_code' => $response->getStatusCode(),
                    'middleware_time' => round(($finish - $start) * 1_000),
                    'ip' => $this->getStringParam($serverParams, 'HTTP_X_REAL_IP') ?? '',
                    'user_agent' => substr($request->getHeaderLine('HTTP_USER_AGENT'), 0, 255),
                    'preferred_language' => substr($request->getHeaderLine('HTTP_ACCEPT_LANGUAGE'), 0, 255),
                    'referrer' => substr($request->getHeaderLine('HTTP_REFERER'), 0, 255),
                    'utm_source' => $this->getStringParam($queryParams, 'utm_source'),
                    'utm_medium' => $this->getStringParam($queryParams, 'utm_medium'),
                    'utm_campaign' => $this->getStringParam($queryParams, 'utm_campaign'),
                    'utm_term' => $this->getStringParam($queryParams, 'utm_term'),
                    'utm_content' => $this->getStringParam($queryParams, 'utm_content'),
                    'error_trace' => $this->exception
                        ? sprintf(
                            "%s:%s = %s\n%s",
                            $this->exception->getFile(),
                            $this->exception->getLine(),
                            $this->exception->getMessage(),
                            $this->exception->getTraceAsString()
                        )
                        : null,
                ],
            )
            ->apply($this->db->createQueryBuilder())
            ->insert('platform_visitor_raw')
            ->executeStatement();
    }

    /**
     * @param array<mixed> $params
     *
     * @psalm-pure
     */
    private function getStringParam(array $params, string $key): ?string
    {
        return isset($params[$key]) && is_string($params[$key]) && trim($params[$key]) !== ''
            ? trim($params[$key])
            : null;
    }

    /**
     * @psalm-external-mutation-free
     */
    public function setException(Throwable $e): void
    {
        $this->exception = $e;
    }
}
