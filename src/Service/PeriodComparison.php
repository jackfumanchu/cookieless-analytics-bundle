<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class PeriodComparison
{
    private function __construct(
        public readonly int $current,
        public readonly int $previous,
        public readonly float $currentFloat,
        public readonly float $previousFloat,
        public readonly float $changePercent,
    ) {
    }

    public static function from(int $current, int $previous): self
    {
        return new self(
            $current,
            $previous,
            (float) $current,
            (float) $previous,
            self::computeChange($current, $previous),
        );
    }

    public static function fromFloat(float $current, float $previous): self
    {
        return new self(
            (int) $current,
            (int) $previous,
            $current,
            $previous,
            self::computeChange($current, $previous),
        );
    }

    private static function computeChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return 0.0;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
