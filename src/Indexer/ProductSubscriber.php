<?php

namespace FroshTypesense\Indexer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchLanguageProvider;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
class ProductSubscriber
{
    public function __construct(
        private readonly ElasticsearchLanguageProvider $languageProvider,
        private readonly MessageBusInterface           $messageBus,
    )
    {
    }

    public function __invoke(EntityWrittenContainerEvent $event): void
    {
        $events = $event->getEvents();

        if ($events === null) {
            return;
        }

        $primaryKeys = $event->getPrimaryKeys('product');
        if (!empty($primaryKeys)) {
            foreach ($this->languageProvider->getLanguages($event->getContext()) as $language) {
                $languageContext = new Context(
                    new SystemSource(),
                    [],
                    Defaults::CURRENCY,
                    array_filter([$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM])
                );

                $this->messageBus->dispatch(
                    new TypesenseIndexingMessage(
                        'product',
                        'product_' . $language->getId(),
                        $primaryKeys,
                        $languageContext->getLanguageIdChain(),
                    )
                );
            }
        }
    }
}
