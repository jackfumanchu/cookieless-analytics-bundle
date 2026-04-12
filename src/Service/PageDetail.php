<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class PageDetail
{
    /**
     * @param list<array{date: string, count: int, unique: int}> $daily
     * @param list<array{source: string, visits: int}> $referrers
     */
    public function __construct(
        public readonly string $pageUrl,
        public readonly PeriodComparison $views,
        public readonly PeriodComparison $visitors,
        public readonly array $daily,
        public readonly array $referrers,
    ) {}
}
