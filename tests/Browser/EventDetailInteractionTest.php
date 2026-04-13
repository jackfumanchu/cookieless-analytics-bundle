<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Browser;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Panther\Client as PantherClient;
use Symfony\Component\Panther\PantherTestCase;

class EventDetailInteractionTest extends PantherTestCase
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
        $em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();

        $today = new \DateTimeImmutable('today');
        // Create two event types with different occurrence counts for predictable ordering
        for ($i = 0; $i < 5; $i++) {
            $em->persist(AnalyticsEvent::create(
                fingerprint: hash('sha256', "visitor-{$i}"),
                name: 'booking-click',
                value: 'event-' . ($i % 3),
                pageUrl: '/concerts',
                recordedAt: $today,
            ));
        }
        for ($i = 0; $i < 2; $i++) {
            $em->persist(AnalyticsEvent::create(
                fingerprint: hash('sha256', "visitor-{$i}"),
                name: 'download',
                value: 'brochure.pdf',
                pageUrl: '/about',
                recordedAt: $today,
            ));
        }
        $em->flush();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function clicking_event_row_updates_detail_pane_and_highlights_row(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        $client->request('GET', "/analytics/events?from={$from}&to={$today}");
        $client->waitFor('.event-layout', 5);

        // First row (booking-click) should be pre-selected
        $firstRow = $client->getCrawler()->filter('.events-table tbody tr')->first();
        self::assertStringContainsString('selected', $firstRow->attr('class') ?? '');

        // Detail pane should show booking-click
        $detail = $client->getCrawler()->filter('#ca-event-detail')->text();
        self::assertStringContainsString('booking-click', $detail);

        // Click the second row (download) and wait for the detail frame to update
        $client->getCrawler()->filter('.events-table tbody tr')->eq(1)->click();
        $client->waitForElementToContain('#ca-event-detail', 'download', 5);

        // Second row should now be selected
        $rows = $client->getCrawler()->filter('.events-table tbody tr');
        self::assertStringNotContainsString('selected', $rows->eq(0)->attr('class') ?? '');
        self::assertStringContainsString('selected', $rows->eq(1)->attr('class') ?? '');

        // Detail pane should now show download
        $detail = $client->getCrawler()->filter('#ca-event-detail')->text();
        self::assertStringContainsString('download', $detail);
    }

    #[Test]
    public function event_detail_shows_values_breakdown(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        $client->request('GET', "/analytics/events?from={$from}&to={$today}");
        $client->waitFor('.event-layout', 5);

        // Default detail (booking-click) should show value breakdown
        $detail = $client->getCrawler()->filter('#ca-event-detail')->text();
        self::assertStringContainsString('EVENT VALUES', $detail);
        self::assertStringContainsString('event-0', $detail);
    }

    #[Test]
    public function summary_strip_shows_correct_top_event_count(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        $client->request('GET', "/analytics/events?from={$from}&to={$today}");
        $client->waitFor('.summary-strip', 5);

        $summary = $client->getCrawler()->filter('.summary-strip')->text();
        // Most Frequent should show booking-click
        self::assertStringContainsString('booking-click', $summary);
        // Total events should be 7
        self::assertStringContainsString('7', $summary);
    }
}
