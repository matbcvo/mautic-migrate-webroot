<?php

declare(strict_types=1);

namespace Mautic\Composer\Plugin\MigrateWebroot;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Mautic\Composer\Plugin\MigrateWebroot\Command\MigrateWebrootCommand;

final class CommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [
            new MigrateWebrootCommand(),
        ];
    }
}
