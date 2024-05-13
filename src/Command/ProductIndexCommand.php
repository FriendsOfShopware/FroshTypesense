<?php declare(strict_types=1);

namespace FroshTypesense\Command;

use FroshTypesense\Indexer\AbstractIndexer;
use FroshTypesense\Indexer\TypesenseIndexingMessage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Elasticsearch\Framework\ElasticsearchLanguageProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Messenger\MessageBusInterface;
use Typesense\Client;

#[AsCommand(name: 'typesense:product:index', description: 'Index products into Typesense')]
class ProductIndexCommand extends Command
{
    /**
     * @param iterable<AbstractIndexer> $indexers
     */
    public function __construct(
        private readonly Client                        $client,
        #[TaggedIterator('frosh_typesense.indexer')]
        private readonly iterable                      $indexers,
        private readonly ElasticsearchLanguageProvider $languageProvider,
        private readonly IteratorFactory               $iteratorFactory,
        private readonly MessageBusInterface           $messageBus,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $languages = $this->languageProvider->getLanguages(Context::createDefaultContext());

        $io = new SymfonyStyle($input, $output);
        $time = time();

        foreach ($this->indexers as $indexer) {
            $io->block('Indexing ' . $indexer->getName() . '...');

            foreach ($languages as $language) {
                $context = new Context(
                    new SystemSource(),
                    [],
                    Defaults::CURRENCY,
                    array_filter([$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM])
                );

                $alias = $indexer->getName() . '_' . $language->getId();
                $this->client->collections->create(
                    [
                        'name' => $alias . '_' . $time,
                        ...$indexer->getMapping(),
                    ]
                );

                $iterator = $this->iteratorFactory->createIterator($indexer->getName());

                $progressBar = new ProgressBar($output, $iterator->fetchCount());
                $progressBar->start();

                while ($ids = $iterator->fetch()) {
                    $this->messageBus->dispatch(
                        new TypesenseIndexingMessage(
                            $indexer->getName(),
                            $alias,
                            $ids,
                            $context->getLanguageIdChain(),
                        )
                    );

                    $progressBar->advance(count($ids));
                }

                $progressBar->finish();

                $this->client->aliases->upsert(
                    $alias,
                    [
                        'collection_name' => $alias . '_' . $time,
                    ]
                );
            }
        }

        return Command::SUCCESS;
    }
}
