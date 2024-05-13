<?php

namespace FroshTypesense\DependencyInjection;

use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;

class ClientFactory
{
    public static function create(): Client
    {
        return new Client(
            [
                'api_key' => 'fooo',
                'nodes' => [
                    [
                        'host' => 'localhost',
                        'port' => '8108',
                        'protocol' => 'http',
                    ],
                ],
                'client' => new HttplugClient(),
            ]
        );
    }
}
