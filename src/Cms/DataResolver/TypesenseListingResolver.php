<?php

declare(strict_types=1);

namespace FroshTypesense\Cms\DataResolver;

use FroshTypesense\DependencyInjection\ClientFactory;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TypesenseListingResolver extends AbstractCmsElementResolver
{
    public function __construct(
        #[Autowire('%frosh_typesense.token%')]
        private readonly string $token,
        #[Autowire('%frosh_typesense.hosts%')]
        private readonly array $urls)
    {

    }

    public function getType(): string
    {
        return 'typesense-listing';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $slot->getData()?->assign([
            'nodes' => ClientFactory::getNodes($this->urls),
            'apiKey' => $this->token,
            'indexName' => 'product_' . $resolverContext->getSalesChannelContext()->getLanguageId(),
        ]);
    }
}
