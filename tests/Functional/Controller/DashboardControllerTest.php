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

        self::assertResponseRedirects('/analytics/?from=2026-03-01&to=2026-04-10', 302);
    }

    #[Test]
    public function referrers_redirects_when_dates_are_normalized(): void
    {
        $client = static::createClient();

        $client->request('GET', '/analytics/frame/referrers?from=2026-04-31&to=2026-05-10');

        self::assertResponseRedirects('/analytics/frame/referrers?from=2026-05-01&to=2026-05-10', 302);
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

        self::assertResponseStatusCodeSame(302);
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

    #[Test]
    public function pages_view_page_2_shows_second_page_of_results(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 25; $i++) {
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: sprintf('/page-%03d', $i),
                referrer: null,
                viewedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&page=2');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        // Page 2 should have 5 results (25 total, 20 per page)
        // Use '<tr' to match both '<tr>' and '<tr class="selected">'
        self::assertSame(5, substr_count($content, '<tr') - 1);
        // Header should show total distinct pages, not current page count
        self::assertStringContainsString('25 pages tracked', $content);
    }

    #[Test]
    public function pages_view_out_of_bounds_page_clamps_to_last(): void
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
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&page=999');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('/home', $content);
    }

    #[Test]
    public function events_view_with_single_event_shows_detail(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'cta_click',
            value: 'hero-button',
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('cta_click', $content);
        self::assertStringNotContainsString('No events recorded in this period', $content);
    }

    #[Test]
    public function pages_view_page_1_shows_first_results(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 25; $i++) {
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: sprintf('/page-%03d', $i),
                referrer: null,
                viewedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&page=1');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        // Page 1 should show 20 results; mutant max(2,...) forces page 2 and shows only 5
        self::assertSame(20, substr_count($content, '<tr') - 1);
    }

    #[Test]
    public function pages_view_page_0_clamps_to_page_1(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 25; $i++) {
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: sprintf('/page-%03d', $i),
                referrer: null,
                viewedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&page=0');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        // page=0 must clamp to page 1, showing 20 results; mutant max(0,...) allows offset=-20
        self::assertSame(20, substr_count($content, '<tr') - 1);
    }

    #[Test]
    public function pages_view_turbo_frame_detail_returns_selected_page(): void
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
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&selected=%2Fhome', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-page-detail',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('turbo-frame', $content);
        self::assertStringContainsString('id="ca-page-detail"', $content);
        self::assertStringContainsString('/home', $content);
        self::assertStringContainsString('dk-value', $content);
        self::assertStringNotContainsString('pages-table', $content);
        self::assertStringNotContainsString('<!DOCTYPE', $content);
    }

    #[Test]
    public function pages_view_turbo_frame_detail_with_unknown_page_shows_empty(): void
    {
        $client = static::createClient();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&selected=%2Fnonexistent', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-page-detail',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('ca-page-detail', $content);
        self::assertStringContainsString('No page selected', $content);
        self::assertStringNotContainsString('dk-value', $content);
    }

    #[Test]
    public function events_view_turbo_frame_detail_returns_selected_event(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'cta_click',
            value: 'hero-button',
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('b', 64),
            name: 'cta_click',
            value: 'footer-button',
            pageUrl: '/about',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today . '&selected=cta_click', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-event-detail',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('ca-event-detail', $content);
        self::assertStringContainsString('cta_click', $content);
        self::assertStringContainsString('dk-value', $content);
        self::assertStringNotContainsString('events-table', $content);
        self::assertStringNotContainsString('<!DOCTYPE', $content);
    }

    #[Test]
    public function events_view_turbo_frame_detail_with_unknown_event_shows_empty(): void
    {
        $client = static::createClient();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today . '&selected=nonexistent', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-event-detail',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('ca-event-detail', $content);
        self::assertStringContainsString('No event selected', $content);
        self::assertStringNotContainsString('dk-value', $content);
    }

    #[Test]
    public function pages_view_search_with_pagination(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 25; $i++) {
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: sprintf('/blog/post-%03d', $i),
                referrer: null,
                viewedAt: new \DateTimeImmutable('today'),
            ));
        }
        for ($i = 1; $i <= 5; $i++) {
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: sprintf('/about/team-%03d', $i),
                referrer: null,
                viewedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=blog&page=2');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertSame(5, substr_count($content, '<tr') - 1);
        self::assertStringNotContainsString('/about/team', $content);
    }

    // ─── Empty Database Tests ───

    #[Test]
    public function pages_view_with_empty_database_shows_empty_state(): void
    {
        $client = static::createClient();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('No page data for this period', $content);
        self::assertStringContainsString('0 pages tracked', $content);
        self::assertSelectorNotExists('.pages-table');
    }

    #[Test]
    public function events_view_with_empty_database_shows_empty_state(): void
    {
        $client = static::createClient();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('No event data for this period', $content);
        self::assertStringContainsString('0 distinct event', $content);
        self::assertStringContainsString('No event selected', $content);
        self::assertSelectorNotExists('.events-table');
    }

    #[Test]
    public function trends_view_with_empty_database_returns_200(): void
    {
        $client = static::createClient();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');
        $client->request('GET', '/analytics/trends?from=' . $from . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('hero-chart', $content);
        self::assertStringContainsString('numbers-strip', $content);
        // Daily avg should show 0
        self::assertStringContainsString('0', $content);
    }

    #[Test]
    public function overview_frames_with_empty_database_show_empty_state(): void
    {
        $client = static::createClient();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $client->request('GET', '/analytics/frame/top-pages?from=' . $today . '&to=' . $today);
        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('No data for this period', $client->getResponse()->getContent());

        $client->request('GET', '/analytics/frame/events?from=' . $today . '&to=' . $today);
        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('No data for this period', $client->getResponse()->getContent());

        $client->request('GET', '/analytics/frame/referrers?from=' . $today . '&to=' . $today);
        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('No data for this period', $client->getResponse()->getContent());
    }

    // ─── Event Null Values Test ───

    #[Test]
    public function events_view_detail_shows_no_values_recorded_when_all_null(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'page-scroll',
            value: null,
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('b', 64),
            name: 'page-scroll',
            value: null,
            pageUrl: '/about',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('page-scroll', $content);
        self::assertStringContainsString('No values recorded', $content);
    }

    #[Test]
    public function events_view_turbo_frame_detail_shows_no_values_when_null(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'page-scroll',
            value: null,
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today . '&selected=page-scroll', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-event-detail',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('No values recorded', $content);
        self::assertStringNotContainsString('values-list', $content);
    }

    // ─── Special Characters in Search ───

    #[Test]
    public function pages_view_search_with_special_characters(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/blog/hello-world?ref=home&lang=en',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('b', 64),
            pageUrl: '/about',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        // Search with ampersand
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=' . urlencode('ref=home&lang'));
        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('/blog/hello-world', $content);
        self::assertStringNotContainsString('/about', $content);
    }

    #[Test]
    public function pages_view_search_with_html_entities_is_escaped(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/page/<script>alert(1)</script>',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=script');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        // Twig auto-escapes — raw <script> tag must not appear
        self::assertStringNotContainsString('<script>', $content);
        self::assertStringContainsString('&lt;script&gt;', $content);
    }

    #[Test]
    public function pages_view_search_with_no_matches_shows_empty(): void
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
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=nonexistent');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('No pages match your search', $content);
        self::assertStringContainsString('0 results', $content);
    }

    // ─── Direct URL with ?selected= (full page, not Turbo Frame) ───

    #[Test]
    public function pages_view_full_page_with_selected_param_preselects_page(): void
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
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        // Full page load (no Turbo-Frame header) — selected is ignored, first page is pre-selected
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        // Without search, first page should be pre-selected in the detail pane
        self::assertStringContainsString('detail-header', $content);
        self::assertStringContainsString('dk-value', $content);
    }

    #[Test]
    public function events_view_full_page_preselects_top_event(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 0; $i < 3; $i++) {
            $em->persist(AnalyticsEvent::create(
                fingerprint: str_repeat('a', 64),
                name: 'top-event',
                value: 'val-' . $i,
                pageUrl: '/home',
                recordedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('b', 64),
            name: 'rare-event',
            value: null,
            pageUrl: '/about',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        // Top event should be auto-selected in detail pane
        self::assertStringContainsString('top-event', $content);
        self::assertStringContainsString('detail-header', $content);
        self::assertStringContainsString('dk-value', $content);
    }

    // ─── Single-Day Date Range on Trends ───

    #[Test]
    public function trends_view_single_day_range_renders_correctly(): void
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
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        // from == to: single day
        $client->request('GET', '/analytics/trends?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('hero-chart', $content);
        self::assertStringContainsString('numbers-strip', $content);
        self::assertStringContainsString('multiples-grid', $content);
        // Peak and Low day should be the same day
        self::assertStringContainsString('Peak Day', $content);
        self::assertStringContainsString('Low Day', $content);
    }

    // ─── Negative Page Number ───

    #[Test]
    public function pages_view_negative_page_clamps_to_page_1(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 25; $i++) {
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: sprintf('/page-%03d', $i),
                referrer: null,
                viewedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&page=-1');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        // page=-1 must clamp to page 1, showing 20 results
        self::assertSame(20, substr_count($content, '<tr') - 1);
    }

    // ─── Empty String Guard Tests (kills &&/|| mutants) ───

    #[Test]
    public function pages_view_empty_string_search_behaves_like_no_search(): void
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
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        // search= (empty string) should show all pages, same as no search param
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=');

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('/home', $content);
        self::assertStringContainsString('/about', $content);
        // First page should be pre-selected (detail pane populated), not "No page selected"
        self::assertStringContainsString('detail-header', $content);
    }

    #[Test]
    public function pages_view_turbo_frame_detail_with_empty_selected_shows_empty(): void
    {
        $client = static::createClient();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        // selected= (empty string) should show "No page selected"
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&selected=', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-page-detail',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('No page selected', $content);
    }

    #[Test]
    public function events_view_turbo_frame_detail_with_empty_selected_shows_empty(): void
    {
        $client = static::createClient();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        // selected= (empty string) should show "No event selected"
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today . '&selected=', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-event-detail',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('No event selected', $content);
    }

    #[Test]
    public function index_with_only_from_param_does_not_redirect(): void
    {
        $client = static::createClient();

        // Only "from" without "to" — should NOT trigger redirect, should use defaults
        $client->request('GET', '/analytics/?from=2026-04-01');

        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function index_with_only_to_param_does_not_redirect(): void
    {
        $client = static::createClient();

        // Only "to" without "from" — should NOT trigger redirect, should use defaults
        $client->request('GET', '/analytics/?to=2026-04-10');

        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function frame_with_only_from_param_does_not_redirect(): void
    {
        $client = static::createClient();

        $client->request('GET', '/analytics/frame/top-pages?from=2026-04-01');

        self::assertResponseStatusCodeSame(200);
    }

    // ─── Top Event Occurrences (kills index mutant #12) ───

    #[Test]
    public function events_view_summary_shows_top_event_occurrences(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Top event: 5 occurrences
        for ($i = 0; $i < 5; $i++) {
            $em->persist(AnalyticsEvent::create(
                fingerprint: str_repeat('a', 64),
                name: 'cta-click',
                value: 'val-' . $i,
                pageUrl: '/home',
                recordedAt: new \DateTimeImmutable('today'),
            ));
        }
        // Second event: 2 occurrences
        for ($i = 0; $i < 2; $i++) {
            $em->persist(AnalyticsEvent::create(
                fingerprint: str_repeat('b', 64),
                name: 'download',
                value: 'file.pdf',
                pageUrl: '/about',
                recordedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        // Summary strip should show "5 occurrences" for the top event, not "2 occurrences"
        self::assertStringContainsString('5 occurrences', $content);
    }

    // ─── Limit Boundary Tests (kills Increment/DecrementInteger mutants) ───

    #[Test]
    public function pages_view_without_page_param_defaults_to_page_1(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 25; $i++) {
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: sprintf('/page-%03d', $i),
                referrer: null,
                viewedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        // No page= param — default should be page 1, showing 20 results
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertSame(20, substr_count($content, '<tr') - 1);
    }

    #[Test]
    public function events_view_limits_to_50_event_types(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 51; $i++) {
            $em->persist(AnalyticsEvent::create(
                fingerprint: str_repeat('a', 64),
                name: sprintf('event-type-%03d', $i),
                value: null,
                pageUrl: '/home',
                recordedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        // Table should have exactly 50 rows, not 49 or 51
        self::assertSame(50, substr_count($content, '<tr') - 1);
    }

    #[Test]
    public function frame_top_pages_limits_to_10(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 11; $i++) {
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: sprintf('/page-%03d', $i),
                referrer: null,
                viewedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/frame/top-pages?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertSame(10, substr_count($content, 'ranked-row'));
    }

    #[Test]
    public function frame_referrers_limits_to_10(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 11; $i++) {
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: '/home',
                referrer: "https://site-{$i}.com/link",
                viewedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/frame/referrers?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertSame(10, substr_count($content, 'ranked-row'));
    }

    #[Test]
    public function frame_events_limits_to_10(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 11; $i++) {
            $em->persist(AnalyticsEvent::create(
                fingerprint: str_repeat('a', 64),
                name: "event-{$i}",
                value: null,
                pageUrl: '/home',
                recordedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/frame/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertSame(10, substr_count($content, 'ranked-row'));
    }

    #[Test]
    public function event_detail_limits_values_to_10(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 11; $i++) {
            $em->persist(AnalyticsEvent::create(
                fingerprint: str_repeat('a', 64),
                name: 'click',
                value: "val-{$i}",
                pageUrl: '/home',
                recordedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today . '&selected=click', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-event-detail',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertSame(10, substr_count($content, 'value-item'));
    }

    #[Test]
    public function event_detail_limits_pages_to_5(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 6; $i++) {
            $em->persist(AnalyticsEvent::create(
                fingerprint: str_repeat('a', 64),
                name: 'click',
                value: null,
                pageUrl: "/page-{$i}",
                recordedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today . '&selected=click', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-event-detail',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertSame(5, substr_count($content, 'detail-page-item'));
    }

    // ─── Chart Data Structure Tests (kills UnwrapArrayMap/CastInt mutants) ───

    #[Test]
    public function trends_view_chart_data_contains_correct_json_types(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $today = new \DateTimeImmutable('today');
        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: $today,
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('b', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: $today,
        ));
        $em->flush();

        $todayStr = $today->format('Y-m-d');
        $client->request('GET', '/analytics/trends?from=' . $todayStr . '&to=' . $todayStr);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();

        // Extract data-chart-dates-value — should be array of date strings, not objects
        preg_match('/data-chart-dates-value="([^"]*)"/', $content, $datesMatch);
        $dates = json_decode(html_entity_decode($datesMatch[1]), true);
        self::assertIsArray($dates);
        self::assertSame($todayStr, $dates[0]);

        // Extract data-chart-views-value — should be array of ints, not strings or objects
        preg_match('/data-chart-views-value="([^"]*)"/', $content, $viewsMatch);
        $views = json_decode(html_entity_decode($viewsMatch[1]), true);
        self::assertIsArray($views);
        self::assertIsInt($views[0]);
        self::assertSame(2, $views[0]);

        // Extract data-chart-visitors-value — should be array of ints
        preg_match('/data-chart-visitors-value="([^"]*)"/', $content, $visitorsMatch);
        $visitors = json_decode(html_entity_decode($visitorsMatch[1]), true);
        self::assertIsArray($visitors);
        self::assertIsInt($visitors[0]);
    }

    #[Test]
    public function trends_frame_chart_data_contains_correct_json_types(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $today = new \DateTimeImmutable('today');
        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: $today,
        ));
        $em->flush();

        $todayStr = $today->format('Y-m-d');
        $client->request('GET', '/analytics/frame/trends?from=' . $todayStr . '&to=' . $todayStr);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();

        // dates: array of strings
        preg_match('/data-chart-dates-value="([^"]*)"/', $content, $datesMatch);
        $dates = json_decode(html_entity_decode($datesMatch[1]), true);
        self::assertIsString($dates[0]);

        // views: array of ints
        preg_match('/data-chart-views-value="([^"]*)"/', $content, $viewsMatch);
        $views = json_decode(html_entity_decode($viewsMatch[1]), true);
        self::assertIsInt($views[0]);

        // visitors: array of ints
        preg_match('/data-chart-visitors-value="([^"]*)"/', $content, $visitorsMatch);
        $visitors = json_decode(html_entity_decode($visitorsMatch[1]), true);
        self::assertIsInt($visitors[0]);
    }

    #[Test]
    public function page_detail_limits_referrers_to_5(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 6; $i++) {
            $em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: '/home',
                referrer: "https://ref-{$i}.com/path",
                viewedAt: new \DateTimeImmutable('today'),
            ));
        }
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&selected=%2Fhome', [], [], [
            'HTTP_TURBO_FRAME' => 'ca-page-detail',
        ]);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertSame(5, substr_count($content, 'detail-ref-item'));
    }
}
