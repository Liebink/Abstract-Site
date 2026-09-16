<?php

declare(strict_types=1);

namespace LiebAbstractSite\Database\Querier\Contributor\Applier;

use Override;
use LiebAbstractSite\Model\Platform;
use Doctrine\DBAL\Query\QueryBuilder;
use LesDatabase\Query\Builder\Applier\Applier;

final class ContributorApplier implements Applier
{
    private const string SELECT_SLUG_QUERY = <<<'SQL'
(
    case
        when pc.slug_hash <> ''
            then json_build_object(
                'hash', pc.slug_hash,
                'name', pc.slug_name
            )
        else null
    end
) as "slug"
SQL;

    private const string SELECT_BOOKS_AVAILABLE = <<<'SQL'
(
    SELECT count(*)
    FROM product_contributor_link pcl
    WHERE
        pcl.contributor = pc.id
        AND
        pcl.role = 'author'
        AND
        pcl.v_active = true
        AND
        exists (
            SELECT *
            FROM product_book_listing pbl
            WHERE
                pbl.book = pcl.reference_id
                AND
                pbl.platform = :platform
                AND
               pbl.state = 'active'
        )
) as "booksAvailable"
SQL;

    /**
     * @psalm-pure
     */
    public function __construct(private readonly Platform $platform)
    {}

    #[Override]
    public function apply(QueryBuilder $builder): QueryBuilder
    {
        $builder->from('product_contributor', 'pc');

        $builder->select('full_name as "fullName"');
        $builder->addSelect(self::SELECT_SLUG_QUERY);
        $builder->addSelect(self::SELECT_BOOKS_AVAILABLE);
        $builder->setParameter('platform', $this->platform->value);

        $builder->andWhere('pc.archived_on IS NULL');

        return $builder;
    }
}
