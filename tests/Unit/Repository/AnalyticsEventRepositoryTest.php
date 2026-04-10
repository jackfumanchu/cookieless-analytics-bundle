<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AnalyticsEventRepositoryTest extends KernelTestCase
{
    private AnalyticsEventRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $this->em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $this->em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();
        $this->repository = $this->em->getRepository(AnalyticsEvent::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    private function createEvent(string $name, string $date, ?string $value = null): void
    {
        $event = AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: $name,
            value: $value,
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable($date),
        );
        $this->em->persist($event);
        $this->em->flush();
    }

    private function createEventWithFingerprint(string $name, string $fingerprint, string $date, ?string $value = null, string $pageUrl = '/home'): void
    {
        $event = AnalyticsEvent::create(
            fingerprint: $fingerprint,
            name: $name,
            value: $value,
            pageUrl: $pageUrl,
            recordedAt: new \DateTimeImmutable($date),
        );
        $this->em->persist($event);
        $this->em->flush();
    }

    private function createEventOnPage(string $name, string $pageUrl, string $date, ?string $value = null): void
    {
        $event = AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: $name,
            value: $value,
            pageUrl: $pageUrl,
            recordedAt: new \DateTimeImmutable($date),
        );
        $this->em->persist($event);
        $this->em->flush();
    }

    #[Test]
    public function count_by_period_returns_total_events(): void
    {
        $this->createEvent('click-cta', '2026-04-05');
        $this->createEvent('click-cta', '2026-04-06');
        $this->createEvent('signup', '2026-04-07');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        self::assertSame(3, $this->repository->countByPeriod($from, $to));
    }

    #[Test]
    public function find_top_events_returns_sorted_with_distinct_values(): void
    {
        $this->createEvent('click-cta', '2026-04-05', 'hero-button');
        $this->createEvent('click-cta', '2026-04-05', 'footer-button');
        $this->createEvent('click-cta', '2026-04-06', 'hero-button');
        $this->createEvent('signup', '2026-04-06', null);

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        $result = $this->repository->findTopEvents($from, $to, 10);

        self::assertCount(2, $result);
        self::assertSame('click-cta', $result[0]['name']);
        self::assertSame(3, (int) $result[0]['occurrences']);
        self::assertSame(2, (int) $result[0]['distinctValues']);
        self::assertSame('signup', $result[1]['name']);
        self::assertSame(1, (int) $result[1]['occurrences']);
    }

    #[Test]
    public function count_distinct_types_returns_unique_event_names(): void
    {
        $this->createEvent('click-cta', '2026-04-05');
        $this->createEvent('click-cta', '2026-04-06');
        $this->createEvent('signup', '2026-04-06');
        $this->createEvent('download', '2026-04-07');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        self::assertSame(3, $this->repository->countDistinctTypes($from, $to));
    }

    #[Test]
    public function count_unique_actors_returns_distinct_fingerprints(): void
    {
        $fp1 = str_repeat('a', 64);
        $fp2 = str_repeat('b', 64);

        $this->createEventWithFingerprint('click-cta', $fp1, '2026-04-05');
        $this->createEventWithFingerprint('signup', $fp1, '2026-04-06');
        $this->createEventWithFingerprint('click-cta', $fp2, '2026-04-06');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        self::assertSame(2, $this->repository->countUniqueActors($from, $to));
    }

    #[Test]
    public function count_by_day_returns_daily_breakdown(): void
    {
        $this->createEvent('click-cta', '2026-04-05 10:00:00');
        $this->createEvent('signup', '2026-04-05 11:00:00');
        $this->createEvent('click-cta', '2026-04-06 10:00:00');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-06 23:59:59');

        $result = $this->repository->countByDay($from, $to);

        self::assertCount(2, $result);
        self::assertSame('2026-04-05', $result[0]['date']);
        self::assertSame(2, (int) $result[0]['count']);
        self::assertSame('2026-04-06', $result[1]['date']);
        self::assertSame(1, (int) $result[1]['count']);
    }
}
