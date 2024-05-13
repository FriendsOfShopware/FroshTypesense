<?php

namespace FroshTypesense\Indexer;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class TypesenseIndexingMessage implements AsyncMessageInterface
{
    /**
     * @param array<string> $entityName
     */
    public function __construct(
        public readonly string $entityName,
        public  readonly string $indexName,
        public  readonly array $ids,
        public  readonly array $languageIds
    )
    {

    }
}
