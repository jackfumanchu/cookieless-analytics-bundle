<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Integration\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PageViewRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PageViewRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $container = $kernel->getContainer()->get('test.service_container');
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(PageViewRepository::class);
        $this->em->createQuery('DELETE FROM ' . PageView::class)->execute();
    }

    #[Test]
    public function find_top_pages_default_limit_is_20(): void
    {
        $today = new \DateTimeImmutable('today');

        for ($i = 1; $i <= 21; $i++) {
            $this->em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: sprintf('/page-%03d', $i),
                referrer: null,
                viewedAt: $today,
            ));
        }
        $this->em->flush();

        $result = $this->repo->findTopPages($today, $today->setTime(23, 59, 59));

        self::assertCount(20, $result);
    }

    #[Test]
    public function find_top_referrers_default_limit_is_10(): void
    {
        $today = new \DateTimeImmutable('today');

        for ($i = 1; $i <= 11; $i++) {
            $this->em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: '/home',
                referrer: "https://site-{$i}.com/link",
                viewedAt: $today,
            ));
        }
        $this->em->flush();

        $result = $this->repo->findTopReferrers($today, $today->setTime(23, 59, 59));

        self::assertCount(10, $result);
    }

    #[Test]
    public function find_top_pages_with_null_search_returns_all(): void
    {
        $today = new \DateTimeImmutable('today');

        $this->em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: $today,
        ));
        $this->em->persist(PageView::create(
            fingerprint: str_repeat('b', 64),
            pageUrl: '/about',
            referrer: null,
            viewedAt: $today,
        ));
        $this->em->flush();

        // null search must not add a LIKE filter — both pages should appear
        $result = $this->repo->findTopPages($today, $today->setTime(23, 59, 59), 20, null);

        self::assertCount(2, $result);
    }

    #[Test]
    public function count_distinct_pages_with_null_search_returns_all(): void
    {
        $today = new \DateTimeImmutable('today');

        $this->em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: $today,
        ));
        $this->em->persist(PageView::create(
            fingerprint: str_repeat('b', 64),
            pageUrl: '/about',
            referrer: null,
            viewedAt: $today,
        ));
        $this->em->flush();

        // null search must not add a LIKE filter
        $result = $this->repo->countDistinctPages($today, $today->setTime(23, 59, 59), null);

        self::assertSame(2, $result);
    }

    #[Test]
    public function find_top_referrers_for_page_default_limit_is_10(): void
    {
        $today = new \DateTimeImmutable('today');

        for ($i = 1; $i <= 11; $i++) {
            $this->em->persist(PageView::create(
                fingerprint: str_repeat('a', 64),
                pageUrl: '/home',
                referrer: "https://ref-{$i}.com/path",
                viewedAt: $today,
            ));
        }
        $this->em->flush();

        $result = $this->repo->findTopReferrersForPage('/home', $today, $today->setTime(23, 59, 59));

        self::assertCount(10, $result);
    }
}
