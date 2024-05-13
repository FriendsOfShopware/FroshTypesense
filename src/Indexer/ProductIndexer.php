<?php

namespace FroshTypesense\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductIndexer extends AbstractIndexer
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: 'shopware.asset.public')]
        private readonly PackageInterface $assetPackage
    )
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
                    'name' => 'salesChannelIds',
                    'type' => 'string[]',
                ],
                [
                    'name' => 'image',
                    'type' => 'string',
                ],
                [
                    'name' => 'imageSrcSet',
                    'type' => 'string',
                ],
                [
                    'name' => 'imageWidth',
                    'type' => 'int32',
                ],
                [
                    'name' => 'imageHeight',
                    'type' => 'int32',
                ],
                [
                    'name' => 'minPurchase',
                    'type' => 'int32',
                ],
                [
                    'name' => 'price.*',
                    'type' => 'float',
                    'facet' => true
                ],
                [
                    'name' => 'property_.*',
                    'type' => 'string[]',
                    'facet' => true
                ],
                [
                    'name' => 'ratingAverage',
                    'type' => 'float',
                    'facet' => true
                ],
                [
                    'name' => 'stock',
                    'type' => 'int32',
                ],
                [
                    'name' => 'manufacturerName',
                    'type' => 'string',
                    'facet' => true
                ],
                [
                    'name' => 'shippingFree',
                    'type' => 'bool',
                    'facet' => true
                ],
                [
                    'name' => 'isCloseout',
                    'type' => 'bool',
                ],
                [
                    'name' => 'url',
                    'type' => 'string',
                ],
                [
                    'name' => 'displayGroup',
                    'type' => 'string',
                    'facet' => true
                ],
                [
                    'name' => 'childCount',
                    'type' => 'int32',
                ]
            ]
        ];
    }

    public function fetch(array $ids, Context $context): array
    {
        $products = $this->fetchProducts($ids, $context);

        $propertyIds = [];

        foreach ($products as &$product) {
            $values = json_decode($product['propertyIds'], true, 512, \JSON_THROW_ON_ERROR);
            $propertyIds = array_merge($propertyIds, $values);

            $product['propertyIds'] = $values;
        }
        unset($product);

        $propertyIds = array_unique($propertyIds);

        $propertyValues = $this->fetchPropertyValues($propertyIds, $context);

        $data = [];
        foreach ($products as $id => $product) {
            if ($product['displayGroup'] === null) {
                continue;
            }

            $translations = $this->filterToOne(json_decode((string) $product['translation'], true, 512, \JSON_THROW_ON_ERROR));
            $parentTranslations = $this->filterToOne(json_decode((string) $product['translation_parent'], true, 512, \JSON_THROW_ON_ERROR));
            $categories = $this->filterToMany(json_decode((string) $product['categories'], true, 512, \JSON_THROW_ON_ERROR));
            $manufacturer = $this->filterToOne(json_decode((string) $product['manufacturer_translation'], true, 512, \JSON_THROW_ON_ERROR));

            $categoryNames = array_values(array_map(function ($category) use($context) {
                return $this->takeItem('name', $context , $category);
            }, $categories));

            $coverThumbnails = json_decode((string) $product['cover'], true, 512, \JSON_THROW_ON_ERROR);
            $cover = '';
            $coverSrcSet = '';
            $coverWidth = 0;
            $coverHeight = 0;

            foreach ($coverThumbnails as $coverThumbnail) {
                if ($coverWidth === 0) {
                    $cover = $this->assetPackage->getUrl($coverThumbnail['path']);
                    $coverWidth = $coverThumbnail['width'];
                    $coverHeight = $coverThumbnail['height'];
                }

                $coverSrcSet .= $this->assetPackage->getUrl($coverThumbnail['path']) . ' ' . $coverThumbnail['width'] . 'w, ';

                if ($coverWidth < $coverThumbnail['width']) {
                    $coverWidth = $coverThumbnail['width'];
                    $coverHeight = $coverThumbnail['height'];
                }
            }

            if ($coverSrcSet !== '') {
                $coverSrcSet = substr($coverSrcSet, 0, -2);
            }

            $paths = explode('|', $product['seoPathInfo']);

            $prices = json_decode($product['price'], true, 512, \JSON_THROW_ON_ERROR);

            $row = [
                'id' => $id,
                'name' => self::stripText($this->takeItem('name', $context, $translations, $parentTranslations) ?? ''),
                'description' => self::stripText($this->takeItem('description', $context, $translations, $parentTranslations) ?? ''),
                'salesChannelIds' => array_values(array_unique(explode('|', $product['visibilities']))),
                'categories' => $categoryNames,
                'ratingAverage' => (float) $product['ratingAverage'],
                'shippingFree' => (bool) $product['shippingFree'],
                'isCloseout' => (bool) $product['isCloseout'],
                'price' => 10.0,
                'stock' => (int) $product['stock'],
                'manufacturerName' => $this->takeItem('name', $context, $manufacturer),
                'displayGroup' => $product['displayGroup'],
                'minPurchase' => (int) $product['minPurchase'],
                'image' => $cover,
                'imageSrcSet' => $coverSrcSet,
                'imageWidth' => $coverWidth,
                'imageHeight' => $coverHeight,
                'childCount' => (int) $product['childCount'],
                'url' => '/'. $paths[0] ?? '',
            ];

            foreach ($product['propertyIds'] as $propertyId) {
                $propertyValue = $propertyValues[$propertyId] ?? null;

                if ($propertyValue === null) {
                    continue;
                }

                $row['property_' . $propertyValue['property_group_id']] = [
                    $this->takeItem('name', $context, $propertyValue['translation'])
                ];
            }

            foreach ($prices as $price) {
                $row['price_' . $price['currencyId'] . '_gross'] = $price['gross'];
                $row['price_' . $price['currencyId'] . '_net'] = $price['net'];
            }

            $data[] = $row;
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
    IFNULL(p.min_purchase, pp.min_purchase) AS minPurchase,
    p.available AS available,
    p.child_count as childCount,
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
        GROUP_CONCAT(
            DISTINCT
            JSON_OBJECT(
                'id', lower(hex(category_translation.category_id)),
                'languageId', lower(hex(category_translation.language_id)),
                'name', category_translation.name
            )
        ),
        ']'
    ) as categories,

    CONCAT(
        '[',
            GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'path', cover_media.path,
                    'width', cover_media.width,
                    'height', cover_media.height
                )
            ),
        ']'
    ) as cover,

    GROUP_CONCAT(seo_url.seo_path_info SEPARATOR '|') as seoPathInfo,

    IFNULL(p.price, pp.price) AS price,

    IFNULL(p.manufacturer_number, pp.manufacturer_number) AS manufacturerNumber,
    IFNULL(p.available_stock, pp.available_stock) AS availableStock,
    IFNULL(p.rating_average, pp.rating_average) AS ratingAverage,
    IFNULL(p.shipping_free, pp.shipping_free) AS shippingFree,
    IFNULL(p.is_closeout, pp.is_closeout) AS isCloseout,
    IFNULL(p.category_tree, pp.category_tree) AS categoryTree,
    IFNULL(p.category_ids, pp.category_ids) AS categoryIds,
    IFNULL(p.option_ids, pp.option_ids) AS optionIds,
    IFNULL(p.property_ids, pp.property_ids) AS propertyIds,
    IFNULL(p.stock, pp.stock) AS stock,
    IFNULL(p.mark_as_topseller, pp.mark_as_topseller) AS markAsTopseller,
    GROUP_CONCAT(LOWER(HEX(product_visibility.sales_channel_id)) SEPARATOR '|') AS visibilities,
    p.display_group as displayGroup

FROM product p
    LEFT JOIN product pp ON(p.parent_id = pp.id AND pp.version_id = :liveVersionId)
    LEFT JOIN product_visibility ON(product_visibility.product_id = p.visibilities AND product_visibility.product_version_id = p.version_id)
    LEFT JOIN product_translation product_main ON (product_main.product_id = p.id AND product_main.product_version_id = p.version_id AND product_main.language_id IN(:languageIds))
    LEFT JOIN product_translation product_parent ON (product_parent.product_id = p.parent_id AND product_parent.product_version_id = p.version_id AND product_parent.language_id IN(:languageIds))

    LEFT JOIN product_manufacturer_translation on (product_manufacturer_translation.product_manufacturer_id = IFNULL(p.product_manufacturer_id, pp.product_manufacturer_id) AND product_manufacturer_translation.product_manufacturer_version_id = p.version_id AND product_manufacturer_translation.language_id IN(:languageIds))

    LEFT JOIN product_media ON (product_media.id = IFNULL(p.cover, pp.cover) and product_media.version_id = :liveVersionId)
    LEFT JOIN media_thumbnail cover_media ON(cover_media.media_id = product_media.media_id)

    LEFT JOIN product_category ON (product_category.product_id = p.categories AND product_category.product_version_id = p.version_id)
    LEFT JOIN category_translation ON (category_translation.category_id = product_category.category_id AND category_translation.category_version_id = product_category.category_version_id AND category_translation.language_id IN(:languageIds))

    LEFT JOIN seo_url ON (seo_url.foreign_key = p.id AND seo_url.route_name = 'frontend.detail.page' AND seo_url.language_id IN(:languageIds) AND is_canonical = 1)

WHERE p.id IN (:ids)
  AND p.version_id = :liveVersionId
  AND (p.child_count = 0 OR p.parent_id IS NOT NULL OR JSON_EXTRACT(`p`.`variant_listing_config`, "$.displayParent") = 1)

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

    private function fetchPropertyValues(array $propertyIds, Context $context): array
    {
        $sql = <<<'SQL'
SELECT
    LOWER(HEX(property_group_option.id)) as id,
    LOWER(HEX(property_group_option.property_group_id)) as property_group_id,
    CONCAT(
        "[",
        GROUP_CONCAT(
            DISTINCT
            JSON_OBJECT(
                'languageId', LOWER(HEX(property_group_option_translation.language_id)),
                'name', property_group_option_translation.name
            )
        ),
        "]"
    ) as translation

FROM property_group_option
LEFT JOIN property_group_option_translation ON (property_group_option.id = property_group_option_translation.property_group_option_id AND property_group_option_translation.language_id IN (:languageIds))
WHERE id IN (:ids)
GROUP BY property_group_option.id
SQL;

        $data = $this->connection->fetchAllAssociative(
            $sql,
            [
                'ids' => Uuid::fromHexToBytesList($propertyIds),
                'languageIds' => Uuid::fromHexToBytesList($context->getLanguageIdChain()),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
                'languageIds' => ArrayParameterType::BINARY,
            ]
        );

        return array_map(function ($item) {
            return [
                'property_group_id' => $item['property_group_id'],
                'translation' => $this->filterToOne(json_decode($item['translation'], true, 512, \JSON_THROW_ON_ERROR)),
            ];
        }, FetchModeHelper::groupUnique($data));
    }
}
