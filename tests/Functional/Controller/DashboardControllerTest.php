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
        self::assertSelectorExists('turbo-frame#ca-referrers');
        self::assertSelectorExists('.masthead');
        self::assertSelectorExists('.section-nav');
        self::assertSelectorExists('.columns');
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
        $client->request('GET', '/analytics/frame/top-pages?from=' . $today . '&to=' . $today);

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
        $client->request('GET', '/analytics/frame/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('click-cta', $content);
        self::assertStringContainsString('ca-events', $content);
    }

    #[Test]
    public function trends_returns_chart_container_with_data(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/frame/trends?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('ca-trends', $content);
        self::assertStringContainsString('data-chart-dates-value', $content);
        self::assertStringContainsString('data-chart-views-value', $content);
        self::assertStringContainsString('data-chart-visitors-value', $content);
    }

    #[Test]
    public function referrers_returns_source_list(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: 'https://google.com/search',
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('b', 64),
            pageUrl: '/about',
            referrer: 'https://google.com/search',
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('c', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/frame/referrers?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('google.com', $content);
        self::assertStringContainsString('Direct', $content);
        self::assertStringContainsString('ca-referrers', $content);
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
        $client->request('GET', '/analytics/frame/overview?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('2', $content); // 2 page views
        self::assertStringContainsString('ca-overview', $content);
    }

    #[Test]
    public function index_redirects_when_dates_are_normalized(): void
    {
        $client = static::createClient();

        // Feb 29 in a non-leap year rolls to Mar 1
        $client->request('GET', '/analytics/?from=2026-02-29&to=2026-04-10');

        self::assertResponseRedirects('/analytics/?from=2026-03-01&to=2026-04-10');
    }

    #[Test]
    public function referrers_redirects_when_dates_are_normalized(): void
    {
        $client = static::createClient();

        $client->request('GET', '/analytics/frame/referrers?from=2026-04-31&to=2026-05-10');

        self::assertResponseRedirects('/analytics/frame/referrers?from=2026-05-01&to=2026-05-10');
    }

    #[Test]
    public function pages_view_returns_200_with_page_list(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: 'https://google.com/search',
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
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('/home', $content);
        self::assertStringContainsString('/about', $content);
        self::assertStringContainsString('page-layout', $content);
        self::assertStringContainsString('google.com', $content);
    }

    #[Test]
    public function events_view_returns_200_with_event_list(): void
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
            fingerprint: str_repeat('b', 64),
            name: 'click-cta',
            value: 'footer-button',
            pageUrl: '/pricing',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'signup',
            value: null,
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('click-cta', $content);
        self::assertStringContainsString('signup', $content);
        self::assertStringContainsString('event-layout', $content);
        self::assertStringContainsString('summary-strip', $content);
    }

    #[Test]
    public function trends_view_returns_200_with_charts(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 0; $i < 3; $i++) {
            $date = (new \DateTimeImmutable('today'))->modify("-{$i} days");
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: '/home',
                referrer: null,
                viewedAt: $date,
            ));
        }
        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'click-cta',
            value: null,
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $from = (new \DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');
        $to = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/trends?from=' . $from . '&to=' . $to);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('hero-chart', $content);
        self::assertStringContainsString('numbers-strip', $content);
        self::assertStringContainsString('data-chart-dates-value', $content);
    }

    #[Test]
    public function index_does_not_redirect_with_valid_dates(): void
    {
        $client = static::createClient();

        $client->request('GET', '/analytics/?from=2026-04-01&to=2026-04-10');

        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function pages_view_with_search_filters_results(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/en/blog/hello',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('b', 64),
            pageUrl: '/en/about',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=blog');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('/en/blog/hello', $content);
        self::assertStringNotContainsString('/en/about', $content);
    }

    #[Test]
    public function pages_view_redirect_preserves_search_param(): void
    {
        $client = static::createClient();
        $client->request('GET', '/analytics/pages?from=2026-1-1&to=2026-1-31&search=blog');

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('search=blog', $location);
        self::assertStringContainsString('from=2026-01-01', $location);
        self::assertStringContainsString('to=2026-01-31', $location);
    }

    #[Test]
    public function pages_view_turbo_frame_returns_only_list(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/en/blog/hello',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=blog', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-pages-list',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('turbo-frame', $content);
        self::assertStringContainsString('/en/blog/hello', $content);
        self::assertStringNotContainsString('detail-pane', $content);
        self::assertStringNotContainsString('<!DOCTYPE', $content);
    }

    #[Test]
    public function pages_view_with_search_shows_empty_detail_pane(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/en/blog/hello',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=blog');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('No page selected', $content);
        self::assertStringNotContainsString('class="detail-header"', $content);
    }
}
