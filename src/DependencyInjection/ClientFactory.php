<?php

namespace FroshTypesense\DependencyInjection;

use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;

class ClientFactory
{
    /**
     * @param array<string> $urls
     */
    public static function getNodes(array $urls): array
    {
        $nodes = [];

        foreach ($urls as $host) {
            $parsed = parse_url($host);

            if ($parsed === false) {
                throw new \InvalidArgumentException('Invalid URL: ' . $host);
            }

            $scheme = $parsed['scheme'] ?? 'http';

            $nodes[] = [
                'host' => $parsed['host'],
                'port' => $parsed['port'] ?? ($scheme === 'https' ? '443' : '80'),
                'protocol' => $scheme,
            ];
        }

        return $nodes;
    }

    public static function create(
        string $token,
        array $urls
    ): Client
    {
        return new Client(
            [
                'api_key' => $token,
                'nodes' => self::getNodes($urls),
                'client' => new HttplugClient(),
            ]
        );
    }
}
