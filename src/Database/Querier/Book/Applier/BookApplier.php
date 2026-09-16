<?php

declare(strict_types=1);

namespace LesAbstractSite\Database\Querier\Book\Applier;

use Override;
use LesAbstractSite\Model\Platform;
use Doctrine\DBAL\Query\QueryBuilder;
use LesDatabase\Query\Builder\Applier\Applier;

final class BookApplier implements Applier
{
    private const string WHERE_LISTED_QUERY = <<<'SQL'
EXISTS (
    SELECT *
    FROM product_book_listing pbl
    WHERE 
        pbl.book = pb.id 
        AND 
        pbl.platform = :platform
        AND 
        pbl.state = 'active'      
)
SQL;

    private const string WHERE_HAS_COVER_QUERY = <<<'SQL'
EXISTS (
    SELECT *
    FROM product_book_resource pbr
    WHERE
        pbr.book = pb.id 
        AND 
        pbr.role = 'coverFront' 
        AND 
        sequence_number = 0
)
SQL;

    private const string SELECT_TITLE_QUERY = <<<'SQL'
(
    SELECT
        json_build_object(
            'collection', (
                case
                    when pbt.collection_main <> '' then json_build_object(
                        'prefix', pbt.collection_prefix,
                        'main', pbt.collection_main,
                        'sub', pbt.collection_sub
                    )
                    else null
                end
            ),
            'subcollection', (
                case
                    when pbt.subcollection_main <> '' then
                        json_build_object(
                            'prefix', pbt.subcollection_prefix,
                            'main', pbt.subcollection_main,
                            'sub', pbt.subcollection_sub
                        )
                    else null
                end
            ),
            'product', (
                case
                    when pbt.product_main <> ''
                        then json_build_object(
                            'prefix', pbt.product_prefix,
                            'main', pbt.product_main,
                            'sub', pbt.product_sub
                        )
                    else null
                end
            )
        )
    FROM product_book_title pbt
    WHERE
        pbt.book = pb.id
        AND
        pbt.role = 'product'
    LIMIT 1
) as "title"
SQL;

    private const string SELECT_PRICE_QUERY = <<<'SQL'
json_build_object(
    'lowest', json_build_object(
          'exShipping', pb.price_lowest_ex_shipping
    )
) as "price"
SQL;

    private const string SELECT_PLANNING_QUERY = <<<'SQL'
(
    SELECT json_build_object('publication', pbp.date_timestamp)
    FROM product_book_planning pbp
    WHERE
        pbp.book = pb.id
        AND
        pbp.role = 'publication'
    LIMIT 1
) as "planning"
SQL;

    private const string SELECT_COVER_QUERY = <<<'SQL'
json_build_object(
    'front', (
        SELECT 
            json_build_object(
                'path', pbr.path,
                'height', pbr.height,
                'width', pbr.width
            )
        FROM product_book_resource pbr
        WHERE
            pbr.book = pb.id 
            AND 
            pbr.role = 'coverFront' 
            AND 
            sequence_number = 0
        LIMIT 1
    ),
    'back', (
        SELECT 
            json_build_object(
                'path', pbr.path,
                'height', pbr.height,
                'width', pbr.width
            )
        FROM product_book_resource pbr
        WHERE
            pbr.book = pb.id 
            AND 
            pbr.role = 'coverBack' 
            AND 
            sequence_number = 0
        LIMIT 1
    )
) as "cover"
SQL;

    private const string SELECT_REFERENCE_QUERY = <<<'SQL'
(
    SELECT json_build_object('gtin', b_ref.value)
    FROM product_book_reference b_ref 
    WHERE 
        b_ref.book = pb.id 
        AND 
        b_ref.scheme = 'gtin-13'
    LIMIT 1
) as "reference"
SQL;

    private const string SELECT_AUTHORS_QUERY = <<<'SQL'
coalesce(
    (
        SELECT JSON_ARRAYAGG(json_build_object('name', pc.full_name))
        FROM product_contributor pc
        WHERE
            pc.id IN (
                SELECT pcl.contributor
                FROM product_contributor_link pcl
                WHERE
                    pcl.reference_type = 'lieb.product.book'
                    AND
                    pcl.reference_id = pb.id
                    AND
                    pcl.v_active = true
                    AND
                    pcl.role = 'author'
            )
            AND
            pc.archived_on IS NULL
   ),
   '[]'
) as "authors"
SQL;

    private const string SELECT_TOPICS_QUERY = <<<'SQL'
coalesce(
    (
        SELECT
            json_arrayagg(
                json_build_object(
                    'id', t.id,
                    'name', t_loc.name,
                    'link', concat('/onderwerp/', t_loc.slug)
                )
            )
        FROM product_topic t
        INNER JOIN product_topic_link tl ON tl.topic = t.id
        INNER JOIN product_topic_localization t_loc ON t_loc.topic = t.id AND t_loc.locale = 'nl_NL'
        WHERE
            tl.reference_type = 'lieb.product.book'
            AND
            tl.reference_id = pb.id
            AND
            tl.state = 'active'
            AND
            tl.dropped_on IS NULL
    ),
    '[]'
) as "topics"
SQL;
    private const string SELECT_DETAILS_QUERY = <<<'SQL'
coalesce(
    (
        SELECT 
            json_object_agg(
                bd.category,
                json_build_object(
                    'unit', bd.unit,
                    'value', bd.value
                )
            )
        FROM product_book_detail bd
        WHERE bd.book = pb.id
    ),
    '{}'
) as "details"
SQL;
    private const string SELECT_TEXT_LANGUAGE_QUERY = <<<'SQL'
coalesce(
    (
        SELECT json_arrayagg(code)
        FROM product_book_language bl
        WHERE bl.book = pb.id AND bl.role = 'text'
    ),
    '[]'
) as "textLanguage"
SQL;
    private const string SELECT_DESCRIPTION_QUERY = <<<'SQL'
(
    SELECT bst.rendered_html
    FROM product_book_synopsis bs
    INNER JOIN product_book_synopsis_text bst ON bst.book_synopsis = bs.id
    
    WHERE 
        bs.book = pb.id
        AND
        bs.role IN ('annotation', 'primaryCover')
        AND 
        bst.format = 'markdown'
        AND
        bst.convert_state = 'rendered'
        AND
        bst.language IN ('und', 'dut', 'eng')
    
    ORDER BY
        (
            case when (bs.role = 'primaryCover') then 1 else 0 end
            +
            case bst.language
                when 'dut' then 3
                when 'eng' then 2
                else 1
            end
        )
        desc
    
    LIMIT 1
) as "description"
SQL;
    private const string SELECT_SAMPLES_QUERY = <<<'SQL'
coalesce(
    (
        SELECT 
            json_arrayagg(
                json_build_object(
                    'path', br.path,
                    'height', br.height,
                    'width', br.width
                )
                ORDER BY br.sequence_number asc
--                 LIMIT 9
            )
        FROM product_book_resource br
        WHERE br.book = pb.id AND br.role = 'sample'
    ),
    '[]'
) as "samples"
SQL;
    private const string SELECT_IMPRINTS_QUERY = <<<'SQL'
coalesce(
    (
        SELECT 
            json_arrayagg(
                json_build_object(
                    'link', case
                        when i.slug_hash <> ''
                            then concat(
                                '/imprint/',
                                i.slug_hash,
                                '/',
                                i.slug_name
                            )
                        else null
                    end,
                    'slug', (
                        case 
                            when i.slug_hash <> ''
                                then json_build_object(
                                    'hash', i.slug_hash,
                                    'name', i.slug_name
                                )
                            else null
                        end
                    ),
                    'name', i.name
                )
            )
        FROM product_imprint i
        WHERE
            i.id IN (
                SELECT il.imprint
                FROM product_imprint_link il
                WHERE
                    il.reference_type = 'lieb.product.book'
                    AND
                    il.reference_id = pb.id
                    AND
                    il.dropped_on IS NULL
            )
            AND
            i.archived_on IS NULL
    ),
    '[]'
) as "imprints"
SQL;
    private const string SELECT_PUBISHERS_QUERY = <<<'SQL'
coalesce(
    (
        SELECT
            json_arrayagg(
                json_build_object(
                    'link', case
                        when p.slug_hash <> ''
                            then concat(
                                '/uitgever/',
                                p.slug_hash,
                                '/',
                                p.slug_name
                            )
                        else null
                    end,
                    'slug', (
                        case
                            when p.slug_hash <> ''
                                then json_build_object(
                                    'hash', p.slug_hash,
                                    'name', p.slug_name
                                )
                            else null
                        end
                    ),
                    'name', p.name
                )
            )
        FROM product_publisher p
        WHERE
            p.id IN (
                SELECT pl.publisher
                FROM product_publisher_link pl
                WHERE
                    pl.reference_type = 'lieb.product.book'
                    AND
                    pl.reference_id = pb.id
                    AND
                    pl.dropped_on IS NULL
            )
            AND
            p.archived_on IS NULL
    ),
    '[]'
) as "publishers"
SQL;
    private const string SELECT_OFFERS_QUERY = <<<'SQL'
coalesce(
    (
        SELECT
            json_arrayagg(
                json_build_object(
                    'supplier', json_build_object(
                        'name', ps.name
                    ),
                    'condition', pbo.condition,
                    'price', json_build_object(
                         'base', pbo.price_ex_shipping,
                         'shipping', (
                             SELECT
                                json_object_agg(
                                    psdc.country,
                                    json_build_object(
                                        'cost', psdc.cost,
                                        'until', psdc.until
                                    )
                                )
                             FROM product_supplier_delivery_cost psdc
                             WHERE psdc.supplier = pbo.supplier_id
                                LIMIT 1
                         )
                    ),
                    'buyLink', (
                        SELECT
                            concat(
                                '/boek/',
                                pbr.value,
                                '/koop/',
                                ps.slug
                            )
                        FROM product_book_reference pbr
                        WHERE
                            pbr.book = pbo.book
                            AND
                            pbr.scheme = 'gtin-13'
                            AND
                            pbr.sub_scheme = 'none'
                        LIMIT 1
                    )
                )
            ORDER BY 
                (
                    SELECT count(*)
                    FROM product_supplier_delivery_cost psdc
                    WHERE psdc.supplier = pbo.supplier_id
                ) desc,
                (
                    SELECT sum(psdc.cost)
                    FROM product_supplier_delivery_cost psdc
                    WHERE psdc.supplier = pbo.supplier_id
                ) 
            )
        FROM product_book_offer pbo
        INNER JOIN product_supplier ps ON ps.id = pbo.supplier_id
        WHERE
            pbo.book = pb.id
            AND
            pbo.state = 'available'
    ),
    '[]'
) as "offers"
SQL;

    private const string SELECT_RANKINGS = <<<'SQL'
json_build_object(
    'bestseller', json_build_object(
        'koken', coalesce(
            (
                SELECT
                    json_build_object(
                        'top1', coalesce(sum(case when pbr.position = 1 then 1 else 0 end), 0),
                        'top3', coalesce(sum(case when pbr.position <= 3 then 1 else 0 end), 0),
                        'top7', coalesce(sum(case when pbr.position <= 7 then 1 else 0 end), 0),
                        'top10', coalesce(sum(case when pbr.position <= 10 then 1 else 0 end), 0)
                    )
                FROM product_book_ranking pbr
                WHERE pbr.book = pb.id AND pbr.source = 'bestseller60.koken'
            ),
            json_build_object(
                'top1', 0,
                'top3', 0,
                'top7', 0,
                'top10', 0
            )
        )
    )

) as "rankings"
SQL;

    /**
     * @psalm-pure
     */
    public function __construct(private readonly Platform $platform)
    {}

    #[Override]
    public function apply(QueryBuilder $builder): QueryBuilder
    {
        $builder
            ->select(
                'pb.id',
                'pb.format',
                self::SELECT_TITLE_QUERY,
                self::SELECT_PRICE_QUERY,
                self::SELECT_COVER_QUERY,
                self::SELECT_REFERENCE_QUERY,
                self::SELECT_AUTHORS_QUERY,
                self::SELECT_TOPICS_QUERY,
                self::SELECT_RANKINGS,
                self::SELECT_PLANNING_QUERY,
                self::SELECT_DETAILS_QUERY,
                self::SELECT_TEXT_LANGUAGE_QUERY,
                self::SELECT_DESCRIPTION_QUERY,
                self::SELECT_SAMPLES_QUERY,
                self::SELECT_IMPRINTS_QUERY,
                self::SELECT_PUBISHERS_QUERY,
                self::SELECT_OFFERS_QUERY,
            );

        $builder->from('product_book', 'pb');

        $builder->andWhere(self::WHERE_LISTED_QUERY);
        $builder->setParameter('platform', $this->platform->value);
        $builder->andWhere('pb.price_lowest_ex_shipping IS NOT NULL');

        $builder->andWhere(self::WHERE_HAS_COVER_QUERY);

        return $builder;
    }
}
