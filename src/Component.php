<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\BaseComponent;

class Component extends BaseComponent
{
    public function run(): void
    {
        $app = new SklikExtractor($this->getConfig(), $this->getLogger());
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
