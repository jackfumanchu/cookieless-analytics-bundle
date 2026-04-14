<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Routing;

use Jackfumanchu\CookielessAnalyticsBundle\Routing\RouteLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;

class RouteLoaderTest extends TestCase
{
    private AttributeRouteControllerLoader $attributeLoader;

    protected function setUp(): void
    {
        $this->attributeLoader = new AttributeRouteControllerLoader();
    }

    #[Test]
    public function it_registers_collect_routes_with_prefix(): void
    {
        $loader = new RouteLoader($this->attributeLoader, '/ca', '/analytics', true);

        $routes = ($loader)();

        self::assertNotNull($routes->get('cookieless_analytics_collect'));
        self::assertSame('/ca/collect', $routes->get('cookieless_analytics_collect')->getPath());
    }

    #[Test]
    public function it_registers_event_routes_with_prefix(): void
    {
        $loader = new RouteLoader($this->attributeLoader, '/ca', '/analytics', true);

        $routes = ($loader)();

        self::assertNotNull($routes->get('cookieless_analytics_event'));
        self::assertSame('/ca/event', $routes->get('cookieless_analytics_event')->getPath());
    }

    #[Test]
    public function it_registers_dashboard_routes_with_prefix(): void
    {
        $loader = new RouteLoader($this->attributeLoader, '/ca', '/analytics', true);

        $routes = ($loader)();

        self::assertSame('/analytics/', $routes->get('cookieless_analytics_dashboard')->getPath());
        self::assertSame('/analytics/pages', $routes->get('cookieless_analytics_dashboard_pages_view')->getPath());
        self::assertSame('/analytics/events', $routes->get('cookieless_analytics_dashboard_events_view')->getPath());
        self::assertSame('/analytics/trends', $routes->get('cookieless_analytics_dashboard_trends_view')->getPath());
    }

    #[Test]
    public function it_registers_dashboard_frame_routes_with_prefix(): void
    {
        $loader = new RouteLoader($this->attributeLoader, '/ca', '/analytics', true);

        $routes = ($loader)();

        self::assertSame('/analytics/frame/overview', $routes->get('cookieless_analytics_dashboard_overview')->getPath());
        self::assertSame('/analytics/frame/trends', $routes->get('cookieless_analytics_dashboard_trends')->getPath());
        self::assertSame('/analytics/frame/top-pages', $routes->get('cookieless_analytics_dashboard_top_pages')->getPath());
        self::assertSame('/analytics/frame/referrers', $routes->get('cookieless_analytics_dashboard_referrers')->getPath());
        self::assertSame('/analytics/frame/events', $routes->get('cookieless_analytics_dashboard_events')->getPath());
    }

    #[Test]
    public function it_excludes_dashboard_routes_when_disabled(): void
    {
        $loader = new RouteLoader($this->attributeLoader, '/ca', '/analytics', false);

        $routes = ($loader)();

        self::assertNotNull($routes->get('cookieless_analytics_collect'));
        self::assertNotNull($routes->get('cookieless_analytics_event'));
        self::assertNull($routes->get('cookieless_analytics_dashboard'));
        self::assertNull($routes->get('cookieless_analytics_dashboard_overview'));
    }
}
