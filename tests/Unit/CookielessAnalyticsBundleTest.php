<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CookielessAnalyticsBundleTest extends KernelTestCase
{
    #[Test]
    public function dashboard_parameters_are_set(): void
    {
        $kernel = static::bootKernel();
        $container = $kernel->getContainer();

        self::assertTrue($container->hasParameter('cookieless_analytics.dashboard_enabled'));
        self::assertTrue($container->getParameter('cookieless_analytics.dashboard_enabled'));
        self::assertSame('/analytics', $container->getParameter('cookieless_analytics.dashboard_prefix'));
        self::assertSame('ROLE_ANALYTICS', $container->getParameter('cookieless_analytics.dashboard_role'));
        self::assertNull($container->getParameter('cookieless_analytics.dashboard_layout'));

        static::ensureKernelShutdown();
    }
}
