<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PageViewRepositoryTest extends KernelTestCase
{
    private PageViewRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $this->em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $this->em->createQuery('DELETE FROM ' . PageView::class)->execute();
        $this->repository = $this->em->getRepository(PageView::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    private function createPageView(string $url, string $fingerprint, string $date, ?string $referrer = null): void
    {
        $pageView = PageView::create(
            fingerprint: $fingerprint,
            pageUrl: $url,
            referrer: $referrer,
            viewedAt: new \DateTimeImmutable($date),
        );
        $this->em->persist($pageView);
        $this->em->flush();
    }

    #[Test]
    public function count_by_period_returns_total_views(): void
    {
        $this->createPageView('/home', 'aaa', '2026-04-05');
        $this->createPageView('/about', 'bbb', '2026-04-06');
        $this->createPageView('/home', 'aaa', '2026-04-07');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        self::assertSame(3, $this->repository->countByPeriod($from, $to));
    }

    #[Test]
    public function count_by_period_excludes_outside_range(): void
    {
        $this->createPageView('/home', 'aaa', '2026-04-04');
        $this->createPageView('/home', 'aaa', '2026-04-05');
        $this->createPageView('/home', 'aaa', '2026-04-08');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        self::assertSame(1, $this->repository->countByPeriod($from, $to));
    }

    #[Test]
    public function count_unique_visitors_by_period(): void
    {
        $fp = str_repeat('a', 64);
        $fp2 = str_repeat('b', 64);

        $this->createPageView('/home', $fp, '2026-04-05');
        $this->createPageView('/about', $fp, '2026-04-05');
        $this->createPageView('/home', $fp2, '2026-04-06');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        self::assertSame(2, $this->repository->countUniqueVisitorsByPeriod($from, $to));
    }

    #[Test]
    public function find_top_pages_returns_sorted_results(): void
    {
        $fp = str_repeat('a', 64);
        $fp2 = str_repeat('b', 64);

        $this->createPageView('/about', $fp, '2026-04-05');
        $this->createPageView('/home', $fp, '2026-04-05');
        $this->createPageView('/home', $fp2, '2026-04-06');
        $this->createPageView('/home', $fp, '2026-04-06');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        $result = $this->repository->findTopPages($from, $to, 10);

        self::assertCount(2, $result);
        self::assertSame('/home', $result[0]['pageUrl']);
        self::assertSame(3, (int) $result[0]['views']);
        self::assertSame(2, (int) $result[0]['uniqueVisitors']);
        self::assertSame('/about', $result[1]['pageUrl']);
        self::assertSame(1, (int) $result[1]['views']);
    }

    #[Test]
    public function count_by_day_returns_daily_breakdown(): void
    {
        $fp = str_repeat('a', 64);
        $fp2 = str_repeat('b', 64);

        $this->createPageView('/home', $fp, '2026-04-05 10:00:00');
        $this->createPageView('/home', $fp2, '2026-04-05 11:00:00');
        $this->createPageView('/home', $fp, '2026-04-06 10:00:00');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-06 23:59:59');

        $result = $this->repository->countByDay($from, $to);

        self::assertCount(2, $result);
        self::assertSame('2026-04-05', $result[0]['date']);
        self::assertSame(2, (int) $result[0]['count']);
        self::assertSame(2, (int) $result[0]['unique']);
        self::assertSame('2026-04-06', $result[1]['date']);
        self::assertSame(1, (int) $result[1]['count']);
        self::assertSame(1, (int) $result[1]['unique']);
    }

    #[Test]
    public function find_top_referrers_returns_grouped_domains(): void
    {
        $this->createPageView('/home', 'aaa', '2026-04-05', 'https://google.com/search?q=test');
        $this->createPageView('/about', 'bbb', '2026-04-05', 'https://google.com/search?q=other');
        $this->createPageView('/home', 'ccc', '2026-04-06', 'https://twitter.com/post/123');
        $this->createPageView('/home', 'ddd', '2026-04-06', null); // direct traffic

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        $result = $this->repository->findTopReferrers($from, $to, 10);

        self::assertCount(3, $result);
        self::assertSame('google.com', $result[0]['source']);
        self::assertSame(2, (int) $result[0]['visits']);
        self::assertSame('twitter.com', $result[1]['source']);
        self::assertSame(1, (int) $result[1]['visits']);
        self::assertSame('Direct', $result[2]['source']);
        self::assertSame(1, (int) $result[2]['visits']);
    }

    #[Test]
    public function find_top_referrers_for_page_filters_by_url(): void
    {
        $this->createPageView('/docs', 'aaa', '2026-04-05', 'https://google.com/search');
        $this->createPageView('/docs', 'bbb', '2026-04-05', 'https://twitter.com/post');
        $this->createPageView('/home', 'ccc', '2026-04-05', 'https://google.com/search');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        $result = $this->repository->findTopReferrersForPage('/docs', $from, $to, 10);

        self::assertCount(2, $result);
        self::assertSame('google.com', $result[0]['source']);
        self::assertSame(1, (int) $result[0]['visits']);
        self::assertSame('twitter.com', $result[1]['source']);
        self::assertSame(1, (int) $result[1]['visits']);
    }

    #[Test]
    public function count_by_day_for_page_filters_by_url(): void
    {
        $fp = str_repeat('a', 64);
        $fp2 = str_repeat('b', 64);

        $this->createPageView('/docs', $fp, '2026-04-05 10:00:00');
        $this->createPageView('/docs', $fp2, '2026-04-05 11:00:00');
        $this->createPageView('/home', $fp, '2026-04-05 12:00:00');
        $this->createPageView('/docs', $fp, '2026-04-06 10:00:00');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-06 23:59:59');

        $result = $this->repository->countByDayForPage('/docs', $from, $to);

        self::assertCount(2, $result);
        self::assertSame('2026-04-05', $result[0]['date']);
        self::assertSame(2, (int) $result[0]['count']);
        self::assertSame(2, (int) $result[0]['unique']);
        self::assertSame('2026-04-06', $result[1]['date']);
        self::assertSame(1, (int) $result[1]['count']);
        self::assertSame(1, (int) $result[1]['unique']);
    }
}
