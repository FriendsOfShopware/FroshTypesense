<?php

namespace FroshTypesense\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class TypesenseExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->setParameter('frosh_typesense.token', $config['token']);
        $container->setParameter('frosh_typesense.hosts', $config['hosts']);
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration();
    }
}
