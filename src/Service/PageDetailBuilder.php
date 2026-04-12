<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;

class PageDetailBuilder
{
    public function __construct(
        private readonly PageViewRepository $pageViewRepo,
        private readonly PeriodComparer $periodComparer,
    ) {}

    public function build(string $pageUrl, DateRange $dateRange): ?PageDetail
    {
        $viewCount = $this->pageViewRepo->countByPeriodForPage($pageUrl, $dateRange->from, $dateRange->to);

        if ($viewCount === 0) {
            return null;
        }

        $views = $this->periodComparer->compare(
            $dateRange,
            fn(\DateTimeImmutable $f, \DateTimeImmutable $t) => $this->pageViewRepo->countByPeriodForPage($pageUrl, $f, $t),
        );
        $visitors = $this->periodComparer->compare(
            $dateRange,
            fn(\DateTimeImmutable $f, \DateTimeImmutable $t) => $this->pageViewRepo->countUniqueVisitorsByPeriodForPage($pageUrl, $f, $t),
        );
        $daily = $this->pageViewRepo->countByDayForPage($pageUrl, $dateRange->from, $dateRange->to);
        $referrers = $this->pageViewRepo->findTopReferrersForPage($pageUrl, $dateRange->from, $dateRange->to, 5);

        return new PageDetail($pageUrl, $views, $visitors, $daily, $referrers);
    }
}
