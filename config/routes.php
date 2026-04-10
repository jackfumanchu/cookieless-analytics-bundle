<?php

declare(strict_types=1);

use Jackfumanchu\CookielessAnalyticsBundle\Controller\CollectController;
use Jackfumanchu\CookielessAnalyticsBundle\Controller\EventController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import(CollectController::class, 'attribute')
        ->prefix('%cookieless_analytics.collect_prefix%');

    $routes->import(EventController::class, 'attribute')
        ->prefix('%cookieless_analytics.collect_prefix%');
};
