<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Browser;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Panther\Client as PantherClient;
use Symfony\Component\Panther\PantherTestCase;

class PageDetailInteractionTest extends PantherTestCase
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

        $today = new \DateTimeImmutable('today');
        // Create two pages with different view counts so they appear in predictable order
        for ($i = 0; $i < 5; $i++) {
            $em->persist(PageView::create(
                fingerprint: hash('sha256', "visitor-{$i}"),
                pageUrl: '/home',
                referrer: 'https://google.com/search',
                viewedAt: $today,
            ));
        }
        for ($i = 0; $i < 2; $i++) {
            $em->persist(PageView::create(
                fingerprint: hash('sha256', "visitor-{$i}"),
                pageUrl: '/about',
                referrer: null,
                viewedAt: $today,
            ));
        }
        $em->flush();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function clicking_row_updates_detail_pane_and_highlights_row(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $client->request('GET', "/analytics/pages?from={$today}&to={$today}");
        $client->waitFor('.page-layout', 5);

        // First row (/home) should be pre-selected
        $firstRow = $client->getCrawler()->filter('.pages-table tbody tr')->first();
        self::assertStringContainsString('selected', $firstRow->attr('class') ?? '');

        // Detail pane should show /home
        $detail = $client->getCrawler()->filter('#ca-page-detail')->text();
        self::assertStringContainsString('/home', $detail);

        // Click the second row (/about)
        $client->getCrawler()->filter('.pages-table tbody tr')->eq(1)->click();
        usleep(1_500_000); // Wait for Turbo Frame to load

        // Second row should now be selected
        $rows = $client->getCrawler()->filter('.pages-table tbody tr');
        self::assertStringNotContainsString('selected', $rows->eq(0)->attr('class') ?? '');
        self::assertStringContainsString('selected', $rows->eq(1)->attr('class') ?? '');

        // Detail pane should now show /about
        $detail = $client->getCrawler()->filter('#ca-page-detail')->text();
        self::assertStringContainsString('/about', $detail);
    }

    #[Test]
    public function row_highlight_persists_after_search(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $client->request('GET', "/analytics/pages?from={$today}&to={$today}");
        $client->waitFor('.page-layout', 5);

        // Click second row (/about)
        $client->getCrawler()->filter('.pages-table tbody tr')->eq(1)->click();
        usleep(1_500_000);

        // Type a search that matches /about
        $searchInput = $client->getCrawler()->filter('.search-input');
        $searchInput->sendKeys('about');
        usleep(1_000_000); // Wait for debounce + Turbo Frame load

        // /about row should still be highlighted after search re-renders the list
        $rows = $client->getCrawler()->filter('.pages-table tbody tr');
        self::assertCount(1, $rows, 'Search should filter to 1 result');
        self::assertStringContainsString('selected', $rows->first()->attr('class') ?? '');
    }
}
