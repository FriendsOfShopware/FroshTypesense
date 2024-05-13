<?php

namespace FroshTypesense\Indexer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Elasticsearch\Framework\ElasticsearchLanguageProvider;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Typesense\Client;

#[AsMessageHandler(handles: TypesenseIndexingMessage::class)]
class TypesenseIndexingMessageHandler
{
    /**
     * @param iterable<AbstractIndexer> $indexers
     */
    public function __construct(
        private readonly Client $client,
        #[TaggedIterator('frosh_typesense.indexer')]
        private readonly iterable $indexers,
        private readonly IteratorFactory $iteratorFactory,
        private readonly MessageBusInterface $messageBus,
    )
    {
    }

    public function __invoke(TypesenseIndexingMessage $message): void
    {
        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            $message->languageIds
        );

        foreach ($this->indexers as $indexer) {
            if ($indexer->getName() !== $message->indexName) {
                continue;
            }

            $data = $indexer->fetch($message->ids, $context);

            $this->client->collections[$message->indexName]->documents->import($data);
        }
    }
}
