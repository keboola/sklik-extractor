<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ConfigDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
            ->scalarNode('#token')->isRequired()->cannotBeEmpty()->end()
            ->arrayNode('accounts')->scalarPrototype()->end()->end()
            ->arrayNode('reports')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('resource')->isRequired()->end()
                        ->arrayNode('restrictionFilter')->isRequired()->scalarPrototype()->end()->end()
                        ->arrayNode('displayOptions')->isRequired()->scalarPrototype()->end()->end()
                        ->arrayNode('displayColumns')->isRequired()->scalarPrototype()->end()->end()
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
