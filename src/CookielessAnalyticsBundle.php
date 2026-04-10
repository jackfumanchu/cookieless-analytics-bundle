<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle;

use Jackfumanchu\CookielessAnalyticsBundle\Controller\CollectController;
use Jackfumanchu\CookielessAnalyticsBundle\Service\FingerprintGenerator;
use Jackfumanchu\CookielessAnalyticsBundle\Service\UrlSanitizer;
use Jackfumanchu\CookielessAnalyticsBundle\Twig\CookielessAnalyticsExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class CookielessAnalyticsBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
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
    }

    /** @param array{collect_prefix: string, strip_query_params: list<string>} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('cookieless_analytics.collect_prefix', $config['collect_prefix']);

        $services = $container->services();

        $services->set(FingerprintGenerator::class);

        $services->set(UrlSanitizer::class)
            ->arg('$stripParams', $config['strip_query_params']);

        $services->set(CollectController::class)
            ->arg('$fingerprintGenerator', service(FingerprintGenerator::class))
            ->arg('$urlSanitizer', service(UrlSanitizer::class))
            ->arg('$entityManager', service('doctrine.orm.entity_manager'))
            ->tag('controller.service_arguments');

        $services->set(CookielessAnalyticsExtension::class)
            ->arg('$collectUrl', $config['collect_prefix'])
            ->tag('twig.extension');
    }
}
