<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Controller\DashboardController;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
use Jackfumanchu\CookielessAnalyticsBundle\Service\EventDetailBuilder;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PageDetailBuilder;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparer;
use Jackfumanchu\CookielessAnalyticsBundle\Service\TrendsStatsCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

class DashboardControllerAccessTest extends TestCase
{
    private DashboardController $controller;

    protected function setUp(): void
    {
        $authChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(false);

        $this->controller = new DashboardController(
            $this->createStub(Environment::class),
            $this->createStub(DateRangeResolver::class),
            $this->createStub(PageViewRepository::class),
            $this->createStub(AnalyticsEventRepository::class),
            $this->createStub(PeriodComparer::class),
            $this->createStub(TrendsStatsCalculator::class),
            $this->createStub(PageDetailBuilder::class),
            $this->createStub(EventDetailBuilder::class),
            $authChecker,
            'ROLE_ANALYTICS',
            null,
        );
    }

    #[Test]
    public function index_throws_access_denied_when_not_granted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller->index(Request::create('/'));
    }

    #[Test]
    public function pages_view_throws_access_denied_when_not_granted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller->pagesView(Request::create('/pages'));
    }

    #[Test]
    public function events_view_throws_access_denied_when_not_granted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller->eventsView(Request::create('/events'));
    }

    #[Test]
    public function trends_view_throws_access_denied_when_not_granted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller->trendsView(Request::create('/trends'));
    }
}
