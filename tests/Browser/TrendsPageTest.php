<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Browser;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Panther\Client as PantherClient;
use Symfony\Component\Panther\PantherTestCase;

class TrendsPageTest extends PantherTestCase
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
        for ($i = 0; $i < 7; $i++) {
            $date = $today->modify("-{$i} days");
            $em->persist(PageView::create(
                fingerprint: hash('sha256', "visitor-{$i}"),
                pageUrl: '/home',
                referrer: null,
                viewedAt: $date,
            ));
        }
        $em->persist(AnalyticsEvent::create(
            fingerprint: hash('sha256', 'visitor-0'),
            name: 'booking-click',
            value: null,
            pageUrl: '/home',
            recordedAt: $today,
        ));
        $em->flush();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function trends_page_renders_chart_and_numbers(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');

        $client->request('GET', "/analytics/trends?from={$from}&to={$today}");
        $client->waitFor('.hero-chart', 5);

        // Chart should render
        $client->waitFor('.uplot', 5);
        $uplotCount = count($client->getCrawler()->filter('.uplot'));
        self::assertGreaterThanOrEqual(1, $uplotCount, 'Trends page should render at least one chart');

        // Numbers strip should be visible with stats
        $numbers = $client->getCrawler()->filter('.numbers-strip')->text();
        self::assertStringContainsString('PEAK DAY', $numbers);
        self::assertStringContainsString('LOW DAY', $numbers);
        self::assertStringContainsString('DAILY AVG', $numbers);
    }

    #[Test]
    public function trends_page_shows_metric_breakdown_cards(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');

        $client->request('GET', "/analytics/trends?from={$from}&to={$today}");
        $client->waitFor('.multiples-grid', 5);

        $cards = $client->getCrawler()->filter('.multiple-card');
        self::assertGreaterThanOrEqual(4, count($cards), 'Should have at least 4 metric breakdown cards');

        $gridText = $client->getCrawler()->filter('.multiples-grid')->text();
        self::assertStringContainsString('PAGE VIEWS', $gridText);
        self::assertStringContainsString('DAILY VISITORS', $gridText);
        self::assertStringContainsString('EVENTS', $gridText);
    }
}
