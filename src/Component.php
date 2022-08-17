<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\BaseComponent;

class Component extends BaseComponent
{
    protected function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();

        if (!file_exists($this->getDataDir() . '/out')) {
            mkdir($this->getDataDir() . '/out');
        }
        if (!file_exists($this->getDataDir() . '/out/tables')) {
            mkdir($this->getDataDir() . '/out/tables');
        }

        $api = new SklikApi($this->getLogger());
        $api->loginByToken($config->getToken());
        $userStorage = new UserStorage($this->getDataDir() . '/out/tables');
        $extractor = new Extractor($api, $userStorage, $this->getLogger());
        $extractor->run($config, $config->getLimit());
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
