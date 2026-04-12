<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;

class EventDetailBuilder
{
    public function __construct(
        private readonly AnalyticsEventRepository $eventRepo,
    ) {}

    /**
     * @param list<array{name: string, occurrences: int, distinctValues: int}> $events Pre-fetched top events list
     */
    public function build(string $eventName, DateRange $dateRange, array $events): ?EventDetail
    {
        $match = null;
        foreach ($events as $event) {
            if ($event['name'] === $eventName) {
                $match = $event;
                break;
            }
        }

        if ($match === null) {
            return null;
        }

        $daily = $this->eventRepo->countByDayForEvent($eventName, $dateRange->from, $dateRange->to);
        $values = $this->eventRepo->findValueBreakdown($eventName, $dateRange->from, $dateRange->to, 10);
        $pages = $this->eventRepo->findTopPagesForEvent($eventName, $dateRange->from, $dateRange->to, 5);

        return new EventDetail(
            $eventName,
            (int) $match['occurrences'],
            (int) $match['distinctValues'],
            $daily,
            $values,
            $pages,
        );
    }
}
