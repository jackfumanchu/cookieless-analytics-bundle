<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Integration\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AnalyticsEventRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AnalyticsEventRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $container = $kernel->getContainer()->get('test.service_container');
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(AnalyticsEventRepository::class);
        $this->em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();
    }

    #[Test]
    public function find_top_events_default_limit_is_10(): void
    {
        $today = new \DateTimeImmutable('today');

        for ($i = 1; $i <= 11; $i++) {
            $this->em->persist(AnalyticsEvent::create(
                fingerprint: str_repeat('a', 64),
                name: "event-type-{$i}",
                value: null,
                pageUrl: '/home',
                recordedAt: $today,
            ));
        }
        $this->em->flush();

        $result = $this->repo->findTopEvents($today, $today->setTime(23, 59, 59));

        self::assertCount(10, $result);
    }

    #[Test]
    public function find_value_breakdown_default_limit_is_10(): void
    {
        $today = new \DateTimeImmutable('today');

        for ($i = 1; $i <= 11; $i++) {
            $this->em->persist(AnalyticsEvent::create(
                fingerprint: str_repeat('a', 64),
                name: 'click',
                value: "value-{$i}",
                pageUrl: '/home',
                recordedAt: $today,
            ));
        }
        $this->em->flush();

        $result = $this->repo->findValueBreakdown('click', $today, $today->setTime(23, 59, 59));

        self::assertCount(10, $result);
    }

    #[Test]
    public function find_top_pages_for_event_default_limit_is_10(): void
    {
        $today = new \DateTimeImmutable('today');

        for ($i = 1; $i <= 11; $i++) {
            $this->em->persist(AnalyticsEvent::create(
                fingerprint: str_repeat('a', 64),
                name: 'click',
                value: null,
                pageUrl: "/page-{$i}",
                recordedAt: $today,
            ));
        }
        $this->em->flush();

        $result = $this->repo->findTopPagesForEvent('click', $today, $today->setTime(23, 59, 59));

        self::assertCount(10, $result);
    }
}
