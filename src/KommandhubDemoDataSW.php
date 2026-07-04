<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW;

use Shopware\Core\Framework\Plugin;

class KommandhubDemoDataSW extends Plugin
{
    /**
     * @return bool
     * @codeCoverageIgnore
     */
    public function executeComposerCommands(): bool
    {
        return true;
    }
}
