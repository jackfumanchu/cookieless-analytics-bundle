<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('cookieless_analytics');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('collect_prefix')
                    ->defaultValue('/ca')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('strip_query_params')
                    ->scalarPrototype()->end()
                    ->defaultValue(['token', 'password', 'key', 'secret', 'email'])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
