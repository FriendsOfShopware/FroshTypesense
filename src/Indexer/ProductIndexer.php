<?php

namespace FroshTypesense\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductIndexer extends AbstractIndexer
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'product';
    }

    public function getMapping(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'id',
                    'type' => 'string',
                ],
                [
                    'name' => 'name',
                    'type' => 'string',
                ],
                [
                    'name' => 'description',
                    'type' => 'string',
                ],
                [
                    'name' => 'categories',
                    'type' => 'string[]',
                ],
                [
                    'name' => 'sales_channel_ids',
                    'type' => 'string[]',
                ],

                [
                    'name' => 'price',
                    'type' => 'float',
                ],
                [
                    'name' => 'stock',
                    'type' => 'int32',
                ],
                [
                    'name' => 'manufacturer_name',
                    'type' => 'string[]',
                ]
            ]
        ];
    }

    public function fetch(array $ids, Context $context): array
    {
        $products = $this->fetchProducts($ids, $context);

        $data = [];
        foreach ($products as $id => $product) {
            $translations = $this->filterToOne(json_decode((string) $product['translation'], true, 512, \JSON_THROW_ON_ERROR));
            $parentTranslations = $this->filterToOne(json_decode((string) $product['translation_parent'], true, 512, \JSON_THROW_ON_ERROR));
            $categories = $this->filterToMany(json_decode((string) $product['categories'], true, 512, \JSON_THROW_ON_ERROR));
            $manufacturer = $this->filterToOne(json_decode((string) $product['manufacturer_translation'], true, 512, \JSON_THROW_ON_ERROR));

            $categoryNames = array_values(array_map(function ($category) use($context) {
                return $this->takeItem('name', $context , $category);
            }, $categories));

            $data[] = [
                'id' => $id,
                'name' => self::stripText($this->takeItem('name', $context, $translations, $parentTranslations) ?? ''),
                'description' => self::stripText($this->takeItem('description', $context, $translations, $parentTranslations) ?? ''),
                'sales_channel_ids' => array_values(array_unique(explode('|', $product['visibilities']))),
                'categories' => $categoryNames,
                'price' => 10.0,
                'stock' => (int) $product['stock'],
                'manufacturer_name' => $this->takeItem('name', $context, $manufacturer)
            ];
        }

        return $data;
    }

    /**
     * @param array<string> $ids
     *
     * @return array<mixed>
     */
    private function fetchProducts(array $ids, Context $context): array
    {
        $sql = <<<'SQL'
SELECT
    LOWER(HEX(p.id)) AS id,
    IFNULL(p.active, pp.active) AS active,
    p.available AS available,
    CONCAT(
        '[',
            GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'languageId', lower(hex(product_main.language_id)),
                    'name', product_main.name,
                    'description', product_main.description,
                    'metaTitle', product_main.meta_title,
                    'metaDescription', product_main.meta_description,
                    'customSearchKeywords', product_main.custom_search_keywords,
                    'customFields', product_main.custom_fields
                )
            ),
        ']'
    ) as translation,
    CONCAT(
        '[',
            GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'languageId', lower(hex(product_parent.language_id)),
                    'name', product_parent.name,
                    'description', product_parent.description,
                    'metaTitle', product_parent.meta_title,
                    'metaDescription', product_parent.meta_description,
                    'customSearchKeywords', product_parent.custom_search_keywords,
                    'customFields', product_parent.custom_fields
                )
            ),
        ']'
    ) as translation_parent,
    CONCAT(
        '[',
            GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'languageId', lower(hex(product_manufacturer_translation.language_id)),
                    'name', product_manufacturer_translation.name
                )
            ),
        ']'
    ) as manufacturer_translation,

    CONCAT(
        '[',
        GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'name', tag.name
                )
            ),
        ']'
    ) as tags,

    CONCAT(
        '[',
        GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'id', lower(hex(category_translation.category_id)),
                    'languageId', lower(hex(category_translation.language_id)),
                    'name', category_translation.name
                )
            ),
        ']'
    ) as categories,

    IFNULL(p.manufacturer_number, pp.manufacturer_number) AS manufacturerNumber,
    IFNULL(p.available_stock, pp.available_stock) AS availableStock,
    IFNULL(p.rating_average, pp.rating_average) AS ratingAverage,
    p.product_number as productNumber,
    p.sales,
    LOWER(HEX(IFNULL(p.product_manufacturer_id, pp.product_manufacturer_id))) AS productManufacturerId,
    IFNULL(p.shipping_free, pp.shipping_free) AS shippingFree,
    IFNULL(p.is_closeout, pp.is_closeout) AS isCloseout,
    LOWER(HEX(IFNULL(p.product_media_id, pp.product_media_id))) AS coverId,
    IFNULL(p.weight, pp.weight) AS weight,
    IFNULL(p.length, pp.length) AS length,
    IFNULL(p.height, pp.height) AS height,
    IFNULL(p.width, pp.width) AS width,
    IFNULL(p.release_date, pp.release_date) AS releaseDate,
    IFNULL(p.created_at, pp.created_at) AS createdAt,
    IFNULL(p.category_tree, pp.category_tree) AS categoryTree,
    IFNULL(p.category_ids, pp.category_ids) AS categoryIds,
    IFNULL(p.option_ids, pp.option_ids) AS optionIds,
    IFNULL(p.property_ids, pp.property_ids) AS propertyIds,
    IFNULL(p.tag_ids, pp.tag_ids) AS tagIds,
    LOWER(HEX(IFNULL(p.tax_id, pp.tax_id))) AS taxId,
    IFNULL(p.stock, pp.stock) AS stock,
    IFNULL(p.ean, pp.ean) AS ean,
    IFNULL(p.mark_as_topseller, pp.mark_as_topseller) AS markAsTopseller,
    p.auto_increment as autoIncrement,
    GROUP_CONCAT(LOWER(HEX(product_visibility.sales_channel_id)) SEPARATOR '|') AS visibilities,
    p.display_group as displayGroup,
    IFNULL(p.cheapest_price_accessor, pp.cheapest_price_accessor) as cheapest_price_accessor,
    LOWER(HEX(p.parent_id)) as parentId,
    p.child_count as childCount,
    p.states

FROM product p
    LEFT JOIN product pp ON(p.parent_id = pp.id AND pp.version_id = :liveVersionId)
    LEFT JOIN product_visibility ON(product_visibility.product_id = p.visibilities AND product_visibility.product_version_id = p.version_id)
    LEFT JOIN product_translation product_main ON (product_main.product_id = p.id AND product_main.product_version_id = p.version_id AND product_main.language_id IN(:languageIds))
    LEFT JOIN product_translation product_parent ON (product_parent.product_id = p.parent_id AND product_parent.product_version_id = p.version_id AND product_parent.language_id IN(:languageIds))

    LEFT JOIN product_manufacturer_translation on (product_manufacturer_translation.product_manufacturer_id = IFNULL(p.product_manufacturer_id, pp.product_manufacturer_id) AND product_manufacturer_translation.product_manufacturer_version_id = p.version_id AND product_manufacturer_translation.language_id IN(:languageIds))

    LEFT JOIN product_tag ON (product_tag.product_id = p.tags AND product_tag.product_version_id = p.version_id)
    LEFT JOIN tag ON (tag.id = product_tag.tag_id)

    LEFT JOIN product_category ON (product_category.product_id = p.categories AND product_category.product_version_id = p.version_id)
    LEFT JOIN category_translation ON (category_translation.category_id = product_category.category_id AND category_translation.category_version_id = product_category.category_version_id AND category_translation.language_id IN(:languageIds))

WHERE p.id IN (:ids) AND p.version_id = :liveVersionId AND (p.child_count = 0 OR p.parent_id IS NOT NULL OR JSON_EXTRACT(`p`.`variant_listing_config`, "$.displayParent") = 1)

GROUP BY p.id
SQL;

        $data = $this->connection->fetchAllAssociative(
            $sql,
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'languageIds' => Uuid::fromHexToBytesList($context->getLanguageIdChain()),
                'liveVersionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
                'languageIds' => ArrayParameterType::BINARY,
            ]
        );

        return FetchModeHelper::groupUnique($data);
    }

    private function takeItem(string $key, Context $context, ...$items)
    {
        foreach ($context->getLanguageIdChain() as $languageId) {
            foreach ($items as $item) {
                if (isset($item[$languageId][$key])) {
                    return $item[$languageId][$key];
                }
            }
        }

        return null;
    }

    /**
     * @param array<mixed>[] $items
     *
     * @return array<int|string, mixed>
     */
    private function filterToOne(array $items, string $key = 'languageId'): array
    {
        $filtered = [];

        foreach ($items as $item) {
            // Empty row
            if ($item[$key] === null) {
                continue;
            }

            $filtered[$item[$key]] = $item;
        }

        return $filtered;
    }

    /**
     * @description strip html tags from text and truncate to 32766 characters
     */
    public static function stripText(string $text): string
    {
        // Remove all html elements to save up space
        $text = strip_tags($text);

        if (mb_strlen($text) >= 32766) {
            return mb_substr($text, 0, 32766);
        }

        return $text;
    }

    /**
     * @param array<mixed> $items
     *
     * @return array<mixed>
     */
    private function filterToMany(array $items): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if ($item['id'] === null) {
                continue;
            }

            if (!isset($filtered[$item['id']])) {
                $filtered[$item['id']] = [];
            }

            $filtered[$item['id']][$item['languageId']] = $item;
        }

        return $filtered;
    }
}
