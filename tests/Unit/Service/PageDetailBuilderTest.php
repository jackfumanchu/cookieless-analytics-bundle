<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRange;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PageDetail;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PageDetailBuilder;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparer;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PageDetailBuilderTest extends TestCase
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
    public function returns_null_when_page_has_no_views(): void
    {
        $pageViewRepo = $this->createConfiguredStub(PageViewRepository::class, [
            'countByPeriodForPage' => 0,
        ]);
        $builder = new PageDetailBuilder($pageViewRepo, $this->createStub(PeriodComparer::class));

        $result = $builder->build('/nonexistent', $this->dateRange);

        self::assertNull($result);
    }

    #[Test]
    public function returns_page_detail_with_all_data(): void
    {
        $pageViewRepo = $this->createConfiguredStub(PageViewRepository::class, [
            'countByPeriodForPage' => 5,
            'countByDayForPage' => [['date' => '2026-04-01', 'count' => 3, 'unique' => 2]],
            'findTopReferrersForPage' => [['source' => 'google.com', 'visits' => 3]],
        ]);

        $viewsComparison = PeriodComparison::from(10, 6);
        $visitorsComparison = PeriodComparison::from(4, 2);

        $periodComparer = $this->createMock(PeriodComparer::class);
        $periodComparer->expects(self::exactly(2))
            ->method('compare')
            ->willReturnOnConsecutiveCalls($viewsComparison, $visitorsComparison);

        $builder = new PageDetailBuilder($pageViewRepo, $periodComparer);
        $result = $builder->build('/home', $this->dateRange);

        self::assertInstanceOf(PageDetail::class, $result);
        self::assertSame('/home', $result->pageUrl);
        self::assertSame(10, $result->views->current);
        self::assertSame(4, $result->visitors->current);
        self::assertCount(1, $result->daily);
        self::assertCount(1, $result->referrers);
    }
}
