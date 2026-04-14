<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Routing;

use Jackfumanchu\CookielessAnalyticsBundle\Controller\CollectController;
use Jackfumanchu\CookielessAnalyticsBundle\Controller\DashboardController;
use Jackfumanchu\CookielessAnalyticsBundle\Controller\DashboardFrameController;
use Jackfumanchu\CookielessAnalyticsBundle\Controller\EventController;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Routing\RouteCollection;

class RouteLoader implements RouteLoaderInterface
{
    public function __construct(
        private readonly AttributeRouteControllerLoader $loader,
        private readonly string $collectPrefix,
        private readonly string $dashboardPrefix,
        private readonly bool $dashboardEnabled,
    ) {
    }

    public function __invoke(): RouteCollection
    {
        $routes = new RouteCollection();

        $this->addPrefixedRoutes($routes, CollectController::class, $this->collectPrefix);
        $this->addPrefixedRoutes($routes, EventController::class, $this->collectPrefix);

        if ($this->dashboardEnabled) {
            $this->addPrefixedRoutes($routes, DashboardController::class, $this->dashboardPrefix);
            $this->addPrefixedRoutes($routes, DashboardFrameController::class, $this->dashboardPrefix);
        }

        return $routes;
    }

    private function addPrefixedRoutes(RouteCollection $routes, string $controllerClass, string $prefix): void
    {
        $collection = $this->loader->load($controllerClass);
        $collection->addPrefix($prefix);
        $routes->addCollection($collection);
    }
}
