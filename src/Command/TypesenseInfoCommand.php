<?php

namespace FroshTypesense\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Typesense\Client;

#[AsCommand(
    name: 'frosh:typesense:info',
    description: 'Get information about the Typesense cluster'
)]
class TypesenseInfoCommand extends Command
{
    public function __construct(private readonly Client $client)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $collections = $this->client->collections->retrieve();

        $table = new Table($output);
        $table->setHeaders(['Name', 'Num Documents', 'Num Fields', 'Created At']);

        foreach ($collections as $collection) {
            $table->addRow([
                $collection['name'],
                $collection['num_documents'],
                count($collection['fields']),
                $collection['created_at'],
            ]);
        }

        $table->render();

        return self::SUCCESS;
    }
}
