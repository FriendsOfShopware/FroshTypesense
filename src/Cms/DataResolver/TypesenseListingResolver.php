<?php

declare(strict_types=1);

namespace FroshTypesense\Cms\DataResolver;

use FroshTypesense\DependencyInjection\ClientFactory;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TypesenseListingResolver extends AbstractCmsElementResolver
{
    public function __construct(
        #[Autowire('%frosh_typesense.token%')]
        private readonly string $token,
        #[Autowire('%frosh_typesense.hosts%')]
        private readonly array  $urls)
    {
    }

    public function getType(): string
    {
        return 'typesense-listing';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $collection = new CriteriaCollection();

        $config = $slot->getConfig();

        if (!empty($config['propertyWhitelist']['value'])) {
            $collection->add('propertyWhitelist', PropertyGroupDefinition::class, new Criteria($config['propertyWhitelist']['value']));
        }

        return $collection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $context = $resolverContext->getSalesChannelContext();

        $slot->setData(new ArrayStruct([
            'nodes' => ClientFactory::getNodes($this->urls),
            'apiKey' => $this->token,
            'indexName' => 'product_' . $context->getLanguageId(),
            'priceField' => 'price_' . $context->getCurrencyId() . '_' . $context->getTaxState(),
            'propertyGroups' => [],
        ]));

        if ($collection = $result->get('propertyWhitelist')) {
            $collection->getEntities()->sortByPositions();

            $slot->getData()?->set('propertyGroups', $collection->getEntities()->fmap(function (PropertyGroupEntity $group) {
                return [
                    'id' => $group->getId(),
                    'name' => $group->getTranslation('name'),
                    'displayType' => $group->getDisplayType(),
                    'sortingType' => $group->getSortingType(),
                ];
            }));
        }
    }
}
