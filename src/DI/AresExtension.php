<?php

declare(strict_types=1);

namespace NksHub\NetteAres\DI;

use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use NksHub\NetteAres\AresClient;

class AresExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'cacheTtl' => Expect::string('1 month'),
        ]);
    }

    public function loadConfiguration(): void
    {
        $config = $this->getConfig();
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('client'))
            ->setFactory(AresClient::class)
            ->setArguments([
                'cacheTtl' => $config->cacheTtl,
            ]);
    }
}
