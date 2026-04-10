<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class DateRange
{
    public function __construct(
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
        public readonly \DateTimeImmutable $comparisonFrom,
        public readonly \DateTimeImmutable $comparisonTo,
    ) {
    }
}

class DateRangeResolver
{
    public function resolve(?string $from, ?string $to): DateRange
    {
        $parsedFrom = null;
        $parsedTo = null;

        if ($from !== null && $to !== null) {
            try {
                $parsedFrom = new \DateTimeImmutable($from . ' 00:00:00');
                $parsedTo = new \DateTimeImmutable($to . ' 23:59:59');
            } catch (\Exception) {
                // invalid dates, fall through to default
            }

            if ($parsedFrom !== null && $parsedTo !== null && $parsedFrom > $parsedTo) {
                $parsedFrom = null;
                $parsedTo = null;
            }
        }

        if ($parsedFrom === null || $parsedTo === null) {
            $parsedTo = new \DateTimeImmutable('today 23:59:59');
            $parsedFrom = $parsedTo->modify('-29 days')->setTime(0, 0, 0);
        }

        $interval = $parsedFrom->diff($parsedTo);
        $days = (int) $interval->days + 1;

        $comparisonTo = $parsedFrom->modify('-1 day')->setTime(23, 59, 59);
        $comparisonFrom = $comparisonTo->modify('-' . ($days - 1) . ' days')->setTime(0, 0, 0);

        return new DateRange($parsedFrom, $parsedTo, $comparisonFrom, $comparisonTo);
    }
}
