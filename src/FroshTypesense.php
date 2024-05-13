<?php declare(strict_types=1);

namespace FroshTypesense;

use FroshTypesense\DependencyInjection\TypesenseExtension;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class FroshTypesense extends Plugin
{
    public function executeComposerCommands(): bool
    {
        return true;
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new TypesenseExtension();
    }
}
