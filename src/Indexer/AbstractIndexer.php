<?php

namespace FroshTypesense\Indexer;

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'frosh_typesense.indexer')]
abstract class AbstractIndexer
{
    abstract public function getName(): string;

    abstract public function getMapping(): array;

    abstract public function fetch(array $ids, Context $context): array;
}
