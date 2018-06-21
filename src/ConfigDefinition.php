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
            ->arrayNode('accounts')->scalarPrototype()->end()
            ->booleanNode('allowEmptyStatistics')->defaultFalse()->end()
            ->arrayNode('reports')->children()
                ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                ->arrayNode('primary')->isRequired()->defaultValue([])->end()
                ->scalarNode('resource')->isRequired()->cannotBeEmpty()->end()
                ->arrayNode('restrictionFilter')->isRequired()->defaultValue([])->end()
                ->arrayNode('displayOptions')->isRequired()->defaultValue([])->end()
                ->arrayNode('displayColumns')->isRequired()->cannotBeEmpty()->end()
            ->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
