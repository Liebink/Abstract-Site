<?php

declare(strict_types=1);

namespace LesAbstractSite\Database\Querier\Contributor;

use Override;
use JsonException;
use RuntimeException;
use Doctrine\DBAL\Connection;
use LesAbstractSite\Model\Platform;
use LesAbstractSite\Database\Querier\AbstractQuerier;
use LesAbstractSite\Database\Querier\Contributor\Applier\ContributorApplier;

final class ContributorQuerier extends AbstractQuerier
{
    public function __construct(Connection $db, Platform $platform)
    {
        parent::__construct($db);

        (new ContributorApplier($platform))->apply($this->builder);
    }

    public function andWithBook(string $book, ?string $role = null): self
    {
        $bookLabel = $this->builder->createNamedParameter($book, 'string');
        $roleLabel = $this->builder->createNamedParameter($role, 'string');

        $builder = $this->db->createQueryBuilder();
        $builder
            ->select('*')
            ->from('product_contributor_link', 'pcl')
            ->andWhere("pcl.reference_type = 'lieb.product.book'")
            ->andWhere("pcl.reference_id = {$bookLabel}")
            ->setParameter('book', $book)
            ->andWhere('pcl.v_active = true')
            ->andWhere('pcl.contributor = pc.id');

        if ($role !== null) {
            $builder
                ->andWhere("pcl.role = {$roleLabel}")
                ->setParameter('role', $role);
        }

        $where = <<<SQL
EXISTS ( {$builder->getSQL() })
SQL;

        $this->builder->andWhere($where);

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
            'slug',
        ];
    }
}
