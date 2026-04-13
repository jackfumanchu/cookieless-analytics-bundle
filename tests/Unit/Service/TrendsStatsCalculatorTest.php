<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\TrendsStatsCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TrendsStatsCalculatorTest extends TestCase
{
    private TrendsStatsCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TrendsStatsCalculator();
    }

    #[Test]
    public function compute_returns_stats_from_daily_data(): void
    {
        $daily = [
            ['date' => '2026-04-06', 'count' => '100', 'unique' => '50'],  // Monday
            ['date' => '2026-04-07', 'count' => '200', 'unique' => '80'],  // Tuesday
            ['date' => '2026-04-08', 'count' => '150', 'unique' => '60'],  // Wednesday
            ['date' => '2026-04-09', 'count' => '180', 'unique' => '70'],  // Thursday
            ['date' => '2026-04-10', 'count' => '160', 'unique' => '65'],  // Friday
            ['date' => '2026-04-11', 'count' => '80', 'unique' => '30'],   // Saturday
            ['date' => '2026-04-12', 'count' => '60', 'unique' => '25'],   // Sunday
        ];

        $stats = $this->calculator->compute($daily);

        self::assertSame(['date' => '2026-04-07', 'views' => 200], $stats['peakDay']);
        self::assertSame(['date' => '2026-04-12', 'views' => 60], $stats['lowDay']);
        self::assertSame(133, $stats['dailyAvgViews']); // 930/7
        self::assertSame(54, $stats['dailyAvgVisitors']); // 380/7
        self::assertSame(158, $stats['weekdayAvg']); // 790/5
        self::assertSame(70, $stats['weekendAvg']); // 140/2
    }

    #[Test]
    public function compute_returns_defaults_for_empty_data(): void
    {
        $stats = $this->calculator->compute([]);

        self::assertNull($stats['peakDay']);
        self::assertNull($stats['lowDay']);
        self::assertSame(0, $stats['dailyAvgViews']);
        self::assertSame(0, $stats['dailyAvgVisitors']);
        self::assertSame(0, $stats['weekdayAvg']);
        self::assertSame(0, $stats['weekendAvg']);
    }

    #[Test]
    public function compute_handles_single_day(): void
    {
        $daily = [
            ['date' => '2026-04-07', 'count' => '42', 'unique' => '15'], // Tuesday
        ];

        $stats = $this->calculator->compute($daily);

        self::assertSame(['date' => '2026-04-07', 'views' => 42], $stats['peakDay']);
        self::assertSame(['date' => '2026-04-07', 'views' => 42], $stats['lowDay']);
        self::assertSame(42, $stats['dailyAvgViews']);
        self::assertSame(15, $stats['dailyAvgVisitors']);
        self::assertSame(42, $stats['weekdayAvg']);
        self::assertSame(0, $stats['weekendAvg']);
    }

    #[Test]
    public function compute_handles_weekend_only(): void
    {
        $daily = [
            ['date' => '2026-04-11', 'count' => '80', 'unique' => '30'], // Saturday
            ['date' => '2026-04-12', 'count' => '60', 'unique' => '25'], // Sunday
        ];

        $stats = $this->calculator->compute($daily);

        self::assertSame(0, $stats['weekdayAvg']);
        self::assertSame(70, $stats['weekendAvg']);
    }

    #[Test]
    public function compute_averages_use_round_not_ceil_or_floor(): void
    {
        // 3 days: total views = 10, avg = 3.333... → round=3, ceil=4, floor=3
        // total unique = 7, avg = 2.333... → round=2, ceil=3, floor=2
        // But we need to discriminate round from floor too.
        // 2 weekdays: total = 5, avg = 2.5 → round=3, ceil=3, floor=2
        // 1 weekend: total = 5 → avg = 5 (no ambiguity)
        // Use: total views = 7 over 3 days → 2.333 → round=2, ceil=3, floor=2 (same as floor)
        // Better: total views = 5 over 3 days → 1.667 → round=2, ceil=2, floor=1
        $daily = [
            ['date' => '2026-04-06', 'count' => '1', 'unique' => '1'],  // Monday
            ['date' => '2026-04-07', 'count' => '2', 'unique' => '1'],  // Tuesday
            ['date' => '2026-04-08', 'count' => '2', 'unique' => '3'],  // Wednesday
        ];

        $stats = $this->calculator->compute($daily);

        // views: 5/3 = 1.667 → round = 2 (ceil=2, floor=1)
        self::assertSame(2, $stats['dailyAvgViews']);
        // visitors: 5/3 = 1.667 → round = 2
        self::assertSame(2, $stats['dailyAvgVisitors']);
        // weekday: same 3 weekdays, 5/3 = 1.667 → round = 2
        self::assertSame(2, $stats['weekdayAvg']);
    }

    #[Test]
    public function compute_averages_round_down_when_below_half(): void
    {
        // 3 days: total views = 4, avg = 1.333 → round=1, ceil=2, floor=1
        // This discriminates round from ceil
        $daily = [
            ['date' => '2026-04-06', 'count' => '1', 'unique' => '1'],  // Monday
            ['date' => '2026-04-07', 'count' => '1', 'unique' => '1'],  // Tuesday
            ['date' => '2026-04-08', 'count' => '2', 'unique' => '2'],  // Wednesday
        ];

        $stats = $this->calculator->compute($daily);

        // views: 4/3 = 1.333 → round=1 (ceil=2)
        self::assertSame(1, $stats['dailyAvgViews']);
        // visitors: 4/3 = 1.333 → round=1 (ceil=2)
        self::assertSame(1, $stats['dailyAvgVisitors']);
        // weekday: 4/3 = 1.333 → round=1 (ceil=2) — kills weekdayAvg round→ceil mutant
        self::assertSame(1, $stats['weekdayAvg']);
    }

    #[Test]
    public function compute_weekend_avg_uses_round_not_ceil(): void
    {
        // 3 weekend days with total 4 → 1.333 → round=1, ceil=2, floor=1
        $daily = [
            ['date' => '2026-04-04', 'count' => '1', 'unique' => '1'],  // Saturday
            ['date' => '2026-04-05', 'count' => '1', 'unique' => '1'],  // Sunday
            ['date' => '2026-04-11', 'count' => '2', 'unique' => '1'],  // Saturday
        ];

        $stats = $this->calculator->compute($daily);

        // weekend: 4/3 = 1.333 → round=1 (ceil=2)
        self::assertSame(1, $stats['weekendAvg']);
    }

    #[Test]
    public function compute_weekend_avg_uses_round_not_floor(): void
    {
        // 3 weekend days with total 5 → 1.667 → round=2, floor=1
        $daily = [
            ['date' => '2026-04-04', 'count' => '2', 'unique' => '1'],  // Saturday
            ['date' => '2026-04-05', 'count' => '2', 'unique' => '1'],  // Sunday
            ['date' => '2026-04-11', 'count' => '1', 'unique' => '1'],  // Saturday
        ];

        $stats = $this->calculator->compute($daily);

        // weekend: 5/3 = 1.667 → round=2 (floor=1)
        self::assertSame(2, $stats['weekendAvg']);
    }
}
