<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DateRangeResolverTest extends TestCase
{
    private DateRangeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DateRangeResolver();
    }

    #[Test]
    public function resolve_with_valid_dates_returns_them(): void
    {
        $result = $this->resolver->resolve('2026-04-01', '2026-04-10');

        self::assertEquals(new \DateTimeImmutable('2026-04-01 00:00:00'), $result->from);
        self::assertEquals(new \DateTimeImmutable('2026-04-10 23:59:59'), $result->to);
    }

    #[Test]
    public function resolve_with_null_defaults_to_last_30_days(): void
    {
        $result = $this->resolver->resolve(null, null);

        $expectedTo = new \DateTimeImmutable('today 23:59:59');
        $expectedFrom = $expectedTo->modify('-29 days')->setTime(0, 0, 0);

        self::assertEquals($expectedFrom, $result->from);
        self::assertEquals($expectedTo, $result->to);
    }

    #[Test]
    public function resolve_with_invalid_date_defaults_to_last_30_days(): void
    {
        $result = $this->resolver->resolve('not-a-date', '2026-04-10');

        $expectedTo = new \DateTimeImmutable('today 23:59:59');
        $expectedFrom = $expectedTo->modify('-29 days')->setTime(0, 0, 0);

        self::assertEquals($expectedFrom, $result->from);
        self::assertEquals($expectedTo, $result->to);
    }

    #[Test]
    public function resolve_with_from_after_to_defaults_to_last_30_days(): void
    {
        $result = $this->resolver->resolve('2026-04-10', '2026-04-01');

        $expectedTo = new \DateTimeImmutable('today 23:59:59');
        $expectedFrom = $expectedTo->modify('-29 days')->setTime(0, 0, 0);

        self::assertEquals($expectedFrom, $result->from);
        self::assertEquals($expectedTo, $result->to);
    }

    #[Test]
    public function comparison_period_has_same_duration(): void
    {
        $result = $this->resolver->resolve('2026-04-01', '2026-04-10');

        // 10-day range → comparison is the 10 days before
        self::assertEquals(new \DateTimeImmutable('2026-03-22 00:00:00'), $result->comparisonFrom);
        self::assertEquals(new \DateTimeImmutable('2026-03-31 23:59:59'), $result->comparisonTo);
    }
}
