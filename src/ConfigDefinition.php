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
            ->scalarNode('#token')->end()
            ->scalarNode('username')->end()
            ->scalarNode('#password')->end()
            ->scalarNode('accounts')->end()
            ->arrayNode('reports')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('resource')->isRequired()->end()
                        ->scalarNode('restrictionFilter')->isRequired()->end()
                        ->scalarNode('displayOptions')->isRequired()->end()
                        ->scalarNode('displayColumns')->isRequired()->end()
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
