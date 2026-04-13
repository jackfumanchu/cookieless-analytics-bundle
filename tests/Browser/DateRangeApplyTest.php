<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Browser;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Panther\Client as PantherClient;
use Symfony\Component\Panther\PantherTestCase;

class DateRangeApplyTest extends PantherTestCase
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
        for ($i = 0; $i < 5; $i++) {
            $em->persist(PageView::create(
                fingerprint: hash('sha256', "visitor-{$i}"),
                pageUrl: '/home',
                referrer: null,
                viewedAt: $today->modify("-{$i} days"),
            ));
        }
        $em->flush();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function apply_button_updates_overview_frames(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

        $client->request('GET', "/analytics/?from={$from}&to={$today}");
        $client->waitForElementToContain('#ca-overview', 'PAGE VIEWS', 5);

        // Set the "from" input to today and click Apply via JS
        $client->executeScript(<<<JS
            document.querySelector('input[name="from"]').value = '{$today}';
            document.querySelector('.period-btn-primary').click();
        JS);

        // Wait for frames to reload
        $client->waitForElementToContain('#ca-overview', 'PAGE VIEWS', 5);

        // Masthead date range should reflect the new dates
        $masthead = $client->getCrawler()->filter('.masthead-rule')->text();
        self::assertStringContainsString($today, $masthead);
    }

    #[Test]
    public function apply_button_narrows_range_on_sub_page(): void
    {
        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');
        $newFrom = (new \DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');

        // Start on Pages sub-page with 30D range
        $client->request('GET', "/analytics/pages?from={$from}&to={$today}");
        $client->waitFor('.page-layout', 5);

        // Narrow to 7D range and click Apply via JS
        $client->executeScript(<<<JS
            document.querySelector('input[name="from"]').value = '{$newFrom}';
            document.querySelector('.period-btn-primary').click();
        JS);

        // Turbo.visit triggers full page navigation — wait for it to complete
        usleep(1_500_000);

        // URL should have the narrowed date range
        $currentUrl = $client->getCurrentURL();
        self::assertStringContainsString("from={$newFrom}", $currentUrl);
        self::assertStringContainsString("to={$today}", $currentUrl);
    }
}
