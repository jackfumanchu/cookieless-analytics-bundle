<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Browser;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Panther\Client as PantherClient;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Browser tests require Chrome and chromedriver.
 * Run: php vendor/bin/phpunit --testsuite browser
 */
class DashboardNavigationTest extends PantherTestCase
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

        // Seed minimal data so charts render
        $kernel = static::bootKernel();
        $em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . PageView::class)->execute();
        $em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();

        $today = new \DateTimeImmutable('today');
        for ($i = 0; $i < 3; $i++) {
            $em->persist(PageView::create(
                fingerprint: hash('sha256', "visitor-{$i}"),
                pageUrl: '/home',
                referrer: null,
                viewedAt: $today->modify("-{$i} days"),
            ));
        }
        $em->persist(AnalyticsEvent::create(
            fingerprint: hash('sha256', 'visitor-0'),
            name: 'click-cta',
            value: null,
            pageUrl: '/home',
            recordedAt: $today,
        ));
        $em->flush();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function navigating_between_pages_does_not_duplicate_charts(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        // Go to overview
        $client->request('GET', "/analytics/?from={$from}&to={$today}");
        $client->waitFor('.uplot', 5);
        $uplotCount = count($client->getCrawler()->filter('.uplot'));
        self::assertSame(1, $uplotCount, 'Overview should have exactly 1 chart');

        // Navigate to Pages
        $client->clickLink('Pages');
        $client->waitFor('.page-layout', 5);
        // Wait a moment for any charts to render
        usleep(500_000);
        $uplotCount = count($client->getCrawler()->filter('.uplot'));
        self::assertLessThanOrEqual(2, $uplotCount, 'Pages should have at most 2 charts (list + detail)');

        // Navigate back to Overview
        $client->clickLink('Overview');
        $client->waitFor('.headline-numbers', 5);
        usleep(500_000);
        $uplotCount = count($client->getCrawler()->filter('.uplot'));
        self::assertSame(1, $uplotCount, 'Returning to Overview should still have exactly 1 chart, not accumulated duplicates');
    }

    #[Test]
    public function date_shortcut_updates_nav_links_on_overview(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        $client->request('GET', "/analytics/?from={$from}&to={$today}");
        $client->waitFor('.section-nav', 5);

        // Click "1D" shortcut
        $client->getCrawler()->filter('.period-btn')->first()->click();
        usleep(500_000);

        // Nav links should now have today's date as both from and to
        $pagesLink = $client->getCrawler()->filter('.section-nav a')->eq(1)->attr('href');
        self::assertStringContainsString("from={$today}", $pagesLink, 'Pages nav link should have updated from date');
        self::assertStringContainsString("to={$today}", $pagesLink, 'Pages nav link should have updated to date');
    }

    #[Test]
    public function date_shortcut_reloads_sub_page(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        // Navigate to Pages sub-page
        $client->request('GET', "/analytics/pages?from={$from}&to={$today}");
        $client->waitFor('.page-layout', 5);

        // Click "1D" shortcut — should reload the page with new dates
        $client->getCrawler()->filter('.period-btn')->first()->click();
        usleep(1_000_000);

        // URL should now have today as both from and to
        $currentUrl = $client->getCurrentURL();
        self::assertStringContainsString("from={$today}", $currentUrl, 'URL should have updated from date after shortcut click');
        self::assertStringContainsString("to={$today}", $currentUrl, 'URL should have updated to date after shortcut click');
    }
}
