<?php

declare(strict_types=1);

namespace LesAbstractSite\Database\Querier\Book;

use Override;
use JsonException;
use RuntimeException;
use Doctrine\DBAL\Connection;
use LesAbstractSite\Model\Platform;
use LesAbstractSite\Database\Querier\AbstractQuerier;
use LesAbstractSite\Database\Querier\Book\Applier\BookApplier;

final class BookQuerier extends AbstractQuerier
{
    public function __construct(Connection $db, Platform $platform)
    {
        parent::__construct($db);

        (new BookApplier($platform))->apply($this->builder);
    }

    public function andWithGtin(string $gtin): self
    {
        $label = $this->builder->createNamedParameter($gtin, 'string');
        $query = <<<SQL
exists (
    SELECT *
    FROM product_book_reference b_ref 
    WHERE 
        b_ref.book = pb.id 
        AND 
        b_ref.scheme = 'gtin-13'
        AND
        b_ref.sub_scheme = 'none'
        AND
        b_ref.value = {$label}
)
SQL;

        $this->builder->andWhere($query);

        return $this;
    }

    /**
     * @param string[] $ids
     */
    public function andWithIds(array $ids, bool $sameOrder = true): self
    {
        $where = $cases = [];
        $position = 0;

        foreach ($ids as $id) {
            $label = $this->builder->createNamedParameter($id);

            $where[] = $label;
            $cases[] = "when {$label} then {$position}";
            $position += 1;
        }

        if ($sameOrder) {
            $casesString = implode(PHP_EOL, $cases);
            $orderQuery = <<<SQL
case pb.id
    {$casesString}
end
SQL;

            $this->builder->orderBy($orderQuery, 'asc');
        }

        $where = implode(', ', $where);

        $this->builder->andWhere("pb.id IN ({$where})");

        return $this;
    }

    public function andWithRanking(string $source): self
    {
        $sourceLabel = $this->builder->createNamedParameter($source, 'string');

        $query = <<<SQL
EXISTS (
    SELECT *
    FROM product_book_ranking pbr
    WHERE
        pbr.book = pb.id
        and
        pbr.source = {$sourceLabel}
)
SQL;

        $this->builder->andWhere($query);

        return $this;
    }

    public function andWithPublisher(string $publisher): self
    {
        $label = $this->builder->createNamedParameter($publisher, 'string');
        $query = <<<SQL
EXISTS (
    SELECT *
    FROM product_publisher_link ppl
    WHERE
        ppl.publisher = {$label}
        AND
        ppl.reference_type = 'lieb.product.book'
        AND
        ppl.reference_id = pb.id
        AND
        ppl.dropped_on IS NULL
)
SQL;

        $this->builder->andWhere($query);

        return $this;
    }

    public function andWithImprint(string $imprint): self
    {
        $label = $this->builder->createNamedParameter($imprint, 'string');
        $query = <<<SQL
EXISTS (
    SELECT *
    FROM product_imprint_link pil
    WHERE
        pil.imprint = {$label}
        AND
        pil.reference_type = 'lieb.product.book'
        AND
        pil.reference_id = pb.id
        AND
        pil.dropped_on IS NULL
)
SQL;

        $this->builder->andWhere($query);

        return $this;
    }

    public function andWithContributor(string $contributor, ?string $role): self
    {
        $labelContributor = $this->builder->createNamedParameter($contributor, 'string');

        if (is_string($role)) {
            $labelRole = $this->builder->createNamedParameter($role, 'string');
            $andWhereRole = "pcl.role = {$labelRole}";
        } else {
            $andWhereRole = '1 = 1';
        }

        $query = <<<SQL
EXISTS (
    SELECT *
    FROM product_contributor_link pcl
    WHERE
        pcl.contributor = {$labelContributor}
        AND
        pcl.reference_type = 'lieb.product.book'
        AND
        pcl.reference_id = pb.id
        AND
        pcl.dropped_on IS NULL
        AND
        {$andWhereRole}
)
SQL;

        $this->builder->andWhere($query);

        return $this;
    }

    public function andWithTopic(string $topic): self
    {
        $label = $this->builder->createNamedParameter($topic, 'string');
        $query = <<<SQL
pb.id IN (
    SELECT ptl.reference_id
    FROM product_topic_link ptl
    WHERE
        ptl.reference_type = 'lieb.product.book'
        AND
        ptl.topic = $label
        AND 
        ptl.dropped_on IS NULL
)
SQL;

        $this->builder->andWhere($query);

        return $this;
    }

    /**
     * @param 'asc'|'desc' $direction
     */
    public function addOrderByPublication(string $direction = 'desc'): self
    {
        $this
            ->builder
            ->innerJoin(
                'pb',
                'product_book_planning',
                'pbp',
                "pbp.book = pb.id AND pbp.role = 'publication'",
            )
            ->addOrderBy(
                'pbp.date_timestamp',
                $direction,
            );

        return $this;
    }

    /**
     * @return array<string>
     *
     * @psalm-pure
     */
    #[Override]
    protected function getJsonFields(): array
    {
        return [
            'price',
            'title',
            'planning',
            'cover',
            'reference',
            'authors',
            'topics',
            'details',
            'textLanguage',
            'samples',
            'imprints',
            'publishers',
            'offers',
            'rankings',
        ];
    }
}
