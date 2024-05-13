<?php declare(strict_types=1);

namespace FroshTypesense;

use Shopware\Core\Framework\Plugin;

class FroshTypesense extends Plugin
{
    public function executeComposerCommands(): bool
    {
        return true;
    }
}
