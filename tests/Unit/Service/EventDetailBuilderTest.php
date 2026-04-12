<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRange;
use Jackfumanchu\CookielessAnalyticsBundle\Service\EventDetail;
use Jackfumanchu\CookielessAnalyticsBundle\Service\EventDetailBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EventDetailBuilderTest extends TestCase
{
    private DateRange $dateRange;

    protected function setUp(): void
    {
        $this->dateRange = new DateRange(
            new \DateTimeImmutable('2026-04-01'),
            new \DateTimeImmutable('2026-04-10'),
            new \DateTimeImmutable('2026-03-22'),
            new \DateTimeImmutable('2026-03-31'),
        );
    }

    #[Test]
    public function returns_null_when_event_not_in_list(): void
    {
        $builder = new EventDetailBuilder($this->createStub(AnalyticsEventRepository::class));
        $events = [['name' => 'click', 'occurrences' => 5, 'distinctValues' => 2]];

        $result = $builder->build('nonexistent', $this->dateRange, $events);

        self::assertNull($result);
    }

    #[Test]
    public function returns_event_detail_with_all_data(): void
    {
        $eventRepo = $this->createConfiguredStub(AnalyticsEventRepository::class, [
            'countByDayForEvent' => [['date' => '2026-04-01', 'count' => 5]],
            'findValueBreakdown' => [['value' => 'hero-button', 'count' => 7]],
            'findTopPagesForEvent' => [['pageUrl' => '/home', 'count' => 8]],
        ]);

        $builder = new EventDetailBuilder($eventRepo);
        $events = [
            ['name' => 'click', 'occurrences' => 10, 'distinctValues' => 3],
            ['name' => 'signup', 'occurrences' => 2, 'distinctValues' => 1],
        ];

        $result = $builder->build('click', $this->dateRange, $events);

        self::assertInstanceOf(EventDetail::class, $result);
        self::assertSame('click', $result->name);
        self::assertSame(10, $result->occurrences);
        self::assertSame(3, $result->distinctValues);
        self::assertCount(1, $result->daily);
        self::assertCount(1, $result->values);
        self::assertCount(1, $result->pages);
    }
}
