<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Functional\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . PageView::class)->execute();
        $em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function index_returns_200_with_dashboard_content(): void
    {
        $client = static::createClient();

        $client->request('GET', '/analytics/');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('turbo-frame#ca-overview');
        self::assertSelectorExists('turbo-frame#ca-top-pages');
        self::assertSelectorExists('turbo-frame#ca-events');
        self::assertSelectorExists('turbo-frame#ca-trends');
    }

    #[Test]
    public function index_contains_date_range_selector(): void
    {
        $client = static::createClient();

        $client->request('GET', '/analytics/');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('[data-controller="date-range"]');
        self::assertSelectorExists('input[name="from"]');
        self::assertSelectorExists('input[name="to"]');
    }

    #[Test]
    public function top_pages_returns_table(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('b', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/about',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/top-pages?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('/home', $content);
        self::assertStringContainsString('/about', $content);
        self::assertStringContainsString('ca-top-pages', $content);
    }

    #[Test]
    public function events_returns_table(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'click-cta',
            value: 'hero-button',
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'click-cta',
            value: 'footer-button',
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('click-cta', $content);
        self::assertStringContainsString('ca-events', $content);
    }

    #[Test]
    public function overview_returns_kpi_cards(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('b', 64),
            pageUrl: '/about',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'click-cta',
            value: null,
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/overview?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('2', $content); // 2 page views
        self::assertStringContainsString('ca-overview', $content);
    }
}
