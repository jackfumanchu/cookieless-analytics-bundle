<?php

declare(strict_types=1);

use Jackfumanchu\CookielessAnalyticsBundle\Routing\RouteLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import(RouteLoader::class, 'service');
};
