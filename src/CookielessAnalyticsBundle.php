<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle;

use Jackfumanchu\CookielessAnalyticsBundle\Controller\CollectController;
use Jackfumanchu\CookielessAnalyticsBundle\Controller\EventController;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Service\FingerprintGenerator;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PathExcluder;
use Jackfumanchu\CookielessAnalyticsBundle\Service\UrlSanitizer;
use Jackfumanchu\CookielessAnalyticsBundle\Twig\CookielessAnalyticsExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class CookielessAnalyticsBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();
        $rootNode
            ->children()
            ->scalarNode('collect_prefix')
            ->defaultValue('/ca')
            ->cannotBeEmpty()
            ->end()
            ->arrayNode('strip_query_params')
            ->scalarPrototype()->end()
            ->defaultValue(['token', 'password', 'key', 'secret', 'email'])
            ->end()
            ->arrayNode('exclude_paths')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->booleanNode('dashboard_enabled')
            ->defaultTrue()
            ->end()
            ->scalarNode('dashboard_prefix')
            ->defaultValue('/analytics')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('dashboard_role')
            ->defaultValue('ROLE_ANALYTICS')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('dashboard_layout')
            ->defaultNull()
            ->end()
            ->end();
    }

    /** @param array{collect_prefix: string, strip_query_params: list<string>, exclude_paths: list<string>, dashboard_enabled: bool, dashboard_prefix: string, dashboard_role: string, dashboard_layout: ?string} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('cookieless_analytics.collect_prefix', $config['collect_prefix']);
        $builder->setParameter('cookieless_analytics.dashboard_enabled', $config['dashboard_enabled']);
        $builder->setParameter('cookieless_analytics.dashboard_prefix', $config['dashboard_prefix']);
        $builder->setParameter('cookieless_analytics.dashboard_role', $config['dashboard_role']);
        $builder->setParameter('cookieless_analytics.dashboard_layout', $config['dashboard_layout']);

        $services = $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure();

        $services->set(PageViewRepository::class);

        $services->set(AnalyticsEventRepository::class);

        $services->set(FingerprintGenerator::class);

        $services->set(UrlSanitizer::class)
            ->arg('$stripParams', $config['strip_query_params']);

        $services->set(PathExcluder::class)
            ->arg('$patterns', $config['exclude_paths']);

        $services->set(DateRangeResolver::class);

        $services->set(CollectController::class);

        $services->set(EventController::class);

        $services->set(CookielessAnalyticsExtension::class)
            ->arg('$collectUrl', $config['collect_prefix']);
    }
}
