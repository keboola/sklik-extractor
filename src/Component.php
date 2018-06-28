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

        $api = new SklikApi($config->getToken(), $this->getLogger());
        $userStorage = new UserStorage($this->getDataDir() . '/out/tables');
        $extractor = new Extractor($api, $userStorage, $this->getLogger());
        $extractor->run($config);
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
