<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparison;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PeriodComparisonTest extends TestCase
{
    #[Test]
    public function it_computes_positive_change(): void
    {
        $comparison = PeriodComparison::from(114, 100);

        self::assertSame(114, $comparison->current);
        self::assertSame(100, $comparison->previous);
        self::assertSame(14.0, $comparison->changePercent);
    }

    #[Test]
    public function it_computes_negative_change(): void
    {
        $comparison = PeriodComparison::from(80, 100);

        self::assertSame(80, $comparison->current);
        self::assertSame(100, $comparison->previous);
        self::assertSame(-20.0, $comparison->changePercent);
    }

    #[Test]
    public function it_handles_zero_previous(): void
    {
        $comparison = PeriodComparison::from(50, 0);

        self::assertSame(50, $comparison->current);
        self::assertSame(0, $comparison->previous);
        self::assertSame(0.0, $comparison->changePercent);
    }

    #[Test]
    public function it_rounds_to_one_decimal(): void
    {
        $comparison = PeriodComparison::from(103, 100);

        self::assertSame(3.0, $comparison->changePercent);
    }

    #[Test]
    public function it_supports_float_values(): void
    {
        $comparison = PeriodComparison::fromFloat(3.9, 3.7);

        self::assertSame(3.9, $comparison->currentFloat);
        self::assertSame(3.7, $comparison->previousFloat);
        self::assertSame(5.4, $comparison->changePercent);
    }
}
