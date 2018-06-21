<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\BaseComponent;

class Component extends BaseComponent
{
    public function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();
        $app = new SklikExtractor($config, $this->getLogger(), $this->getDataDir() . '/out/tables');
        $app->execute();
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }
}
