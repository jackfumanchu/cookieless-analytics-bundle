<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class DateRangeResolver
{
    public function resolve(?string $from, ?string $to): DateRange
    {
        $parsedFrom = null;
        $parsedTo = null;

        if ($from !== null && $to !== null) {
            try {
                /** @infection-ignore-all — PHP date parser handles swapped/missing time suffix identically */
                $parsedFrom = new \DateTimeImmutable($from . ' 00:00:00');
                /** @infection-ignore-all — PHP date parser handles swapped time suffix identically */
                $parsedTo = new \DateTimeImmutable($to . ' 23:59:59');
            } catch (\Exception) {
                // invalid dates, fall through to default
            }

            /** @infection-ignore-all — from gets 00:00:00 and to gets 23:59:59; never equal so > vs >= is equivalent */
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
        /** @infection-ignore-all — DateInterval::$days is already int */
        $days = (int) $interval->days + 1;

        $comparisonTo = $parsedFrom->modify('-1 day')->setTime(23, 59, 59);
        $comparisonFrom = $comparisonTo->modify('-' . ($days - 1) . ' days')->setTime(0, 0, 0);

        return new DateRange($parsedFrom, $parsedTo, $comparisonFrom, $comparisonTo);
    }
}
