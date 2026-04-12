<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class EventDetail
{
    /**
     * @param list<array{date: string, count: int}> $daily
     * @param list<array{value: string, count: int}> $values
     * @param list<array{pageUrl: string, count: int}> $pages
     */
    public function __construct(
        public readonly string $name,
        public readonly int $occurrences,
        public readonly int $distinctValues,
        public readonly array $daily,
        public readonly array $values,
        public readonly array $pages,
    ) {}
}
