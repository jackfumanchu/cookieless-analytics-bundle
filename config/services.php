<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Jackfumanchu\CookielessAnalyticsBundle\Controller\CollectController;
use Jackfumanchu\CookielessAnalyticsBundle\Service\FingerprintGenerator;
use Jackfumanchu\CookielessAnalyticsBundle\Service\UrlSanitizer;
use Jackfumanchu\CookielessAnalyticsBundle\Twig\CookielessAnalyticsExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(FingerprintGenerator::class);

    $services->set(UrlSanitizer::class)
        ->arg('$stripParams', param('cookieless_analytics.strip_query_params'));

    $services->set(CollectController::class)
        ->arg('$fingerprintGenerator', service(FingerprintGenerator::class))
        ->arg('$urlSanitizer', service(UrlSanitizer::class))
        ->arg('$entityManager', service('doctrine.orm.entity_manager'))
        ->tag('controller.service_arguments');

    $services->set(CookielessAnalyticsExtension::class)
        ->arg('$collectUrl', param('cookieless_analytics.collect_prefix'))
        ->tag('twig.extension');
};
