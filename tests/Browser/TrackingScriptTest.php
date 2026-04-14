<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Browser;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Panther\Client as PantherClient;
use Symfony\Component\Panther\PantherTestCase;

class TrackingScriptTest extends PantherTestCase
{
    private static function chromeDriverBinary(): string
    {
        return realpath(__DIR__ . '/../../drivers/chromedriver.exe')
            ?: realpath(__DIR__ . '/../../drivers/chromedriver')
            ?: 'chromedriver';
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $kernel = static::bootKernel();

        return $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
    }

    private function clearPageViews(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM ' . PageView::class)->execute();
        $em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();
    }

    /** @return list<array{pageUrl: string, referrer: ?string}> */
    private function getPageViews(EntityManagerInterface $em): array
    {
        // Clear identity map so we see rows written by the web server process
        $em->clear();

        return $em->createQueryBuilder()
            ->select('p.pageUrl', 'p.referrer')
            ->from(PageView::class, 'p')
            ->orderBy('p.viewedAt', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /** @return list<string> */
    private function getPageViewUrls(EntityManagerInterface $em): array
    {
        return array_column($this->getPageViews($em), 'pageUrl');
    }

    #[Test]
    public function initial_page_load_sends_beacon(): void
    {
        $em = $this->getEntityManager();
        $this->clearPageViews($em);
        static::ensureKernelShutdown();

        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');

        $client->request('GET', '/test/home');
        $client->waitFor('#page-title', 5);
        usleep(500_000);

        $views = $this->getPageViews($em);
        self::assertCount(1, $views, 'Exactly one beacon should be sent');
        self::assertSame('/test/home', $views[0]['pageUrl']);
    }

    #[Test]
    public function push_state_navigation_sends_beacon_with_internal_referrer(): void
    {
        $em = $this->getEntityManager();
        $this->clearPageViews($em);
        static::ensureKernelShutdown();

        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');

        $client->request('GET', '/test/home');
        $client->waitFor('#page-title', 5);
        usleep(500_000);

        // Click SPA link (uses pushState, no full reload)
        $client->getCrawler()->filter('#link-about')->click();
        usleep(500_000);

        $views = $this->getPageViews($em);
        self::assertCount(2, $views, 'Two beacons should be sent');
        self::assertSame('/test/home', $views[0]['pageUrl']);
        self::assertSame('/test/about', $views[1]['pageUrl']);
        self::assertSame('/test/home', $views[1]['referrer'], 'SPA navigation should send previous page URL as referrer');
    }

    #[Test]
    public function popstate_navigation_sends_beacon(): void
    {
        $em = $this->getEntityManager();
        $this->clearPageViews($em);
        static::ensureKernelShutdown();

        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');

        $client->request('GET', '/test/home');
        $client->waitFor('#page-title', 5);
        usleep(500_000);

        // Navigate via pushState
        $client->getCrawler()->filter('#link-about')->click();
        usleep(500_000);

        // Go back (triggers popstate)
        $client->executeScript('history.back()');
        usleep(500_000);

        $urls = $this->getPageViewUrls($em);
        self::assertContains('/test/home', $urls, 'Returning via back button should be tracked');
        self::assertContains('/test/about', $urls, 'pushState navigation should be tracked');
        // /test/home should appear twice (initial + back)
        $homeCount = count(array_filter($urls, fn (string $u) => $u === '/test/home'));
        self::assertSame(2, $homeCount, 'Back navigation to /test/home should create a second page view');
    }

    #[Test]
    public function duplicate_push_state_to_same_url_does_not_send_extra_beacon(): void
    {
        $em = $this->getEntityManager();
        $this->clearPageViews($em);
        static::ensureKernelShutdown();

        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');

        $client->request('GET', '/test/home');
        $client->waitFor('#page-title', 5);
        usleep(500_000);

        // pushState to the same URL twice
        $client->executeScript("history.pushState({},'','/test/home')");
        usleep(300_000);
        $client->executeScript("history.pushState({},'','/test/home')");
        usleep(300_000);

        $urls = $this->getPageViewUrls($em);
        $homeCount = count(array_filter($urls, fn (string $u) => $u === '/test/home'));
        self::assertSame(1, $homeCount, 'Duplicate pushState to the same URL should not create extra page views');
    }

    #[Test]
    public function multiple_spa_navigations_send_beacons_with_referrer_chain(): void
    {
        $em = $this->getEntityManager();
        $this->clearPageViews($em);
        static::ensureKernelShutdown();

        static::startWebServer(['port' => 9180, 'webServerDir' => __DIR__ . '/../App/public']);
        $client = PantherClient::createChromeClient(self::chromeDriverBinary(), null, [], 'http://127.0.0.1:9180');

        $client->request('GET', '/test/home');
        $client->waitFor('#page-title', 5);
        usleep(500_000);

        $client->getCrawler()->filter('#link-about')->click();
        usleep(500_000);

        $client->getCrawler()->filter('#link-contact')->click();
        usleep(500_000);

        $views = $this->getPageViews($em);
        self::assertCount(3, $views, 'Each distinct SPA navigation should send exactly one beacon');
        self::assertSame('/test/home', $views[0]['pageUrl']);
        self::assertSame('/test/about', $views[1]['pageUrl']);
        self::assertSame('/test/contact', $views[2]['pageUrl']);
        self::assertSame('/test/home', $views[1]['referrer']);
        self::assertSame('/test/about', $views[2]['referrer']);
    }
}
