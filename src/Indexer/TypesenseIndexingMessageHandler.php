<?php

namespace FroshTypesense\Indexer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
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
        private readonly iterable $indexers
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
            if ($indexer->getName() !== $message->entityName) {
                continue;
            }

            $data = $indexer->fetch($message->ids, $context);

            $this->client->collections[$message->indexName]->documents->delete(['filter_by' => 'id:=' . implode(',', $message->ids)]);

            $results = $this->client->collections[$message->indexName]->documents->import($data, ['action' => 'upsert']);

            $errors = [];

            foreach ($results as $result) {
                if (isset($result['error'])) {
                    $errors[] = $result;
                }
            }

            if (!empty($errors)) {
                dd($errors);
            }
        }
    }
}
