<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Browser;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Panther\Client as PantherClient;
use Symfony\Component\Panther\PantherTestCase;

class OverviewLazyFramesTest extends PantherTestCase
{
    private static function chromeDriverBinary(): string
    {
        return realpath(__DIR__ . '/../../drivers/chromedriver.exe')
            ?: realpath(__DIR__ . '/../../drivers/chromedriver')
            ?: 'chromedriver';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = static::bootKernel();
        $em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . PageView::class)->execute();
        $em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();

        $today = new \DateTimeImmutable('today');
        for ($i = 0; $i < 3; $i++) {
            $em->persist(PageView::create(
                fingerprint: hash('sha256', "visitor-{$i}"),
                pageUrl: '/home',
                referrer: 'https://google.com/search',
                viewedAt: $today->modify("-{$i} days"),
            ));
        }
        $em->persist(PageView::create(
            fingerprint: hash('sha256', 'visitor-0'),
            pageUrl: '/about',
            referrer: null,
            viewedAt: $today,
        ));
        $em->persist(AnalyticsEvent::create(
            fingerprint: hash('sha256', 'visitor-0'),
            name: 'booking-click',
            value: 'summer-fest',
            pageUrl: '/home',
            recordedAt: $today,
        ));
        $em->flush();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function overview_lazy_frames_load_content(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        $client->request('GET', "/analytics/?from={$from}&to={$today}");

        // Wait for lazy frames to load — overview KPIs replace "Loading..." placeholders
        $client->waitForElementToContain('#ca-overview', 'PAGE VIEWS', 5);

        // Overview frame should have loaded with KPI values
        $overview = $client->getCrawler()->filter('#ca-overview')->text();
        self::assertStringContainsString('PAGE VIEWS', $overview);
        self::assertStringContainsString('DAILY VISITORS', $overview);
        self::assertStringNotContainsString('Loading...', $overview);

        // Top pages frame should have loaded
        $client->waitForElementToContain('#ca-top-pages', '/home', 5);
        $topPages = $client->getCrawler()->filter('#ca-top-pages')->text();
        self::assertStringContainsString('/home', $topPages);

        // Events frame should have loaded
        $client->waitForElementToContain('#ca-events', 'booking-click', 5);
        $events = $client->getCrawler()->filter('#ca-events')->text();
        self::assertStringContainsString('booking-click', $events);

        // Referrers frame should have loaded
        $client->waitForElementToContain('#ca-referrers', 'google.com', 5);
        $referrers = $client->getCrawler()->filter('#ca-referrers')->text();
        self::assertStringContainsString('google.com', $referrers);
    }

    #[Test]
    public function overview_chart_renders_after_lazy_load(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        $client->request('GET', "/analytics/?from={$from}&to={$today}");

        // Wait for the chart (uPlot) to render inside the trends frame
        $client->waitFor('.uplot', 5);

        // The trends frame should no longer show loading placeholder
        $trends = $client->getCrawler()->filter('#ca-trends')->text();
        self::assertStringContainsString('TRAFFIC REPORT', $trends);
    }

    #[Test]
    public function date_change_reloads_lazy_frames(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        $client->request('GET', "/analytics/?from={$from}&to={$today}");
        $client->waitForElementToContain('#ca-overview', 'PAGE VIEWS', 5);

        // Click "1D" shortcut — should reload lazy frames with new dates
        $client->getCrawler()->filter('.period-btn')->first()->click();

        // Frames should reload — wait for overview to re-render
        $client->waitForElementToContain('#ca-overview', 'PAGE VIEWS', 5);

        // Verify frames still have content (not stuck on "Loading...")
        $overview = $client->getCrawler()->filter('#ca-overview')->text();
        self::assertStringNotContainsString('Loading...', $overview);
    }
}
