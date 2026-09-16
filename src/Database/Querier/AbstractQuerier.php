<?php

declare(strict_types=1);

namespace LiebAbstractSite\Database\Querier;

use JsonException;
use RuntimeException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

abstract class AbstractQuerier
{
    protected readonly QueryBuilder $builder;

    public function __construct(protected readonly Connection $db)
    {
        $this->builder = $db->createQueryBuilder();
    }

    public function paginate(int $page, int $perPage): self
    {
        $this->builder->setFirstResult(($page - 1) * $perPage);

        return $this->limit($perPage);
    }

    public function limit(int $limit): self
    {
        $this->builder->setMaxResults($limit);

        return $this;
    }

    /**
     * @return array<mixed>|null
     *
     * @throws Exception
     * @throws JsonException
     */
    public function fetch(): ?array
    {
        $result = $this->builder->fetchAssociative();

        if (!is_array($result)) {
            return null;
        }

        return $this->decode($result);
    }

    /**
     * @return array<mixed>
     *
     * @throws Exception
     */
    public function fetchAll(): array
    {
        return array_map(
            $this->decode(...),
            $this->builder->fetchAllAssociative(),
        );
    }

    public function count(): int
    {
        $countBuilder = clone $this->builder;
        $countBuilder->select('COUNT(*)');
        $countBuilder->setFirstResult(0);
        $countBuilder->setMaxResults(1);

        $countBuilder->resetOrderBy();

        $result = $countBuilder->fetchOne();

        if (is_string($result) && ctype_digit($result)) {
            return (int)$result;
        }

        if (is_int($result)) {
            return $result;
        }

        throw new RuntimeException("Unexpected result type");
    }

    /**
     * @throws Exception
     */
    public function getSql(): string
    {
        return $this->builder->getSQL();
    }

    /**
     * @param array<mixed> $result
     *
     * @return array<mixed>
     *
     * @throws JsonException
     *
     * @psalm-mutation-free
     */
    protected function decode(array $result): array
    {
        foreach ($this->getJsonFields() as $field) {
            if (!isset($result[$field])) {
                continue;
            }

            if (!is_string($result[$field])) {
                throw new RuntimeException("Unexpected result type");
            }

            $result[$field] = json_decode(
                $result[$field],
                flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY,
            );
        }

        return $result;
    }

    /**
     * @return array<string>
     *
     * @psalm-pure
     */
    protected function getJsonFields(): array
    {
        return [];
    }
}
