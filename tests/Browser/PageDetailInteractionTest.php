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

        // Click the second row (/about) and wait for the detail frame to update
        $client->getCrawler()->filter('.pages-table tbody tr')->eq(1)->click();
        $client->waitForElementToContain('#ca-page-detail', '/about', 5);

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

        // Click second row (/about) and wait for detail frame
        $client->getCrawler()->filter('.pages-table tbody tr')->eq(1)->click();
        $client->waitForElementToContain('#ca-page-detail', '/about', 5);

        // Type a search that matches /about — wait for debounce + frame load
        $searchInput = $client->getCrawler()->filter('.search-input');
        $searchInput->sendKeys('about');

        // Wait for the search results to filter (only 1 row visible)
        $client->waitFor('.pages-table tbody tr', 5);
        usleep(500_000); // small buffer for Stimulus debounce + frame swap

        // /about row should still be highlighted after search re-renders the list
        $rows = $client->getCrawler()->filter('.pages-table tbody tr');
        self::assertCount(1, $rows, 'Search should filter to 1 result');
        self::assertStringContainsString('selected', $rows->first()->attr('class') ?? '');
    }

    #[Test]
    public function date_change_after_row_click_preserves_content(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        $client->request('GET', "/analytics/pages?from={$from}&to={$today}");
        $client->waitFor('.page-layout', 5);

        // Click a row to set frame.src, wait for detail to load
        $client->getCrawler()->filter('.pages-table tbody tr')->eq(1)->click();
        $client->waitForElementToContain('#ca-page-detail', '/about', 5);

        // Change date range — click "1D" shortcut (triggers Turbo.visit)
        $client->getCrawler()->filter('.period-btn')->first()->click();
        // Wait for the full page reload to complete
        $client->waitFor('.pages-table', 5);

        // Page should not show Turbo "Content missing" error
        // Use executeScript to read from the live DOM (avoids stale element references after Turbo.visit)
        $body = $client->executeScript('return document.body.textContent');
        self::assertStringNotContainsString('Content missing', $body, 'Page should not show Turbo "Content missing" error after date change');
        // The pages table should still be visible
        $tableCount = $client->executeScript('return document.querySelectorAll(".pages-table").length');
        self::assertGreaterThan(0, $tableCount, 'Pages table should be visible after date change');
    }

    #[Test]
    public function search_filters_page_list_via_turbo_frame(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        $client->request('GET', "/analytics/pages?from={$from}&to={$today}");
        $client->waitFor('.page-layout', 5);

        // Both pages should be visible initially
        $rows = $client->getCrawler()->filter('.pages-table tbody tr');
        self::assertCount(2, $rows, 'Should show 2 pages initially');

        // Type "home" in search — debounce fires after 300ms, reloads the Turbo Frame
        $searchInput = $client->getCrawler()->filter('.search-input');
        $searchInput->sendKeys('home');
        usleep(800_000); // wait for debounce + frame reload

        // Only /home should be visible
        $rows = $client->getCrawler()->filter('.pages-table tbody tr');
        self::assertCount(1, $rows, 'Search should filter to 1 result');
        $rowText = $rows->first()->text();
        self::assertStringContainsString('/home', $rowText);
        self::assertStringNotContainsString('/about', $rowText);
    }
}
