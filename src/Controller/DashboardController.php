<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PeriodComparer;
use Jackfumanchu\CookielessAnalyticsBundle\Service\TrendsStatsCalculator;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRange;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

class DashboardController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly DateRangeResolver $dateRangeResolver,
        private readonly PageViewRepository $pageViewRepo,
        private readonly AnalyticsEventRepository $eventRepo,
        private readonly PeriodComparer $periodComparer,
        private readonly TrendsStatsCalculator $trendsStats,
        private readonly ?AuthorizationCheckerInterface $authorizationChecker,
        private readonly string $dashboardRole,
        private readonly ?string $dashboardLayout,
    ) {
    }

    #[Route(path: '/', name: 'cookieless_analytics_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $dateRange = $this->dateRangeResolver->resolve(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/index.html.twig', [
            'from' => $dateRange->from->format('Y-m-d'),
            'to' => $dateRange->to->format('Y-m-d'),
            'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
            'active_nav' => 'overview',
        ]);

        return new Response($html);
    }

    #[Route(path: '/pages', name: 'cookieless_analytics_dashboard_pages_view', methods: ['GET'])]
    public function pagesView(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $search = $request->query->get('search');
        $dateRange = $this->dateRangeResolver->resolve(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $searchTerm = is_string($search) && $search !== '' ? $search : null;
        $pages = $this->pageViewRepo->findTopPages($dateRange->from, $dateRange->to, 50, $searchTerm);
        $totalPages = count($pages);

        // Pre-select the first page for the detail pane (only when not searching)
        $selectedPage = $searchTerm === null ? ($pages[0]['pageUrl'] ?? null) : null;
        $selectedDetail = null;
        if ($selectedPage !== null) {
            $selectedViews = $this->periodComparer->compare(
                $dateRange,
                fn (\DateTimeImmutable $f, \DateTimeImmutable $t) => $this->pageViewRepo->countByPeriodForPage($selectedPage, $f, $t),
            );
            $selectedVisitors = $this->periodComparer->compare(
                $dateRange,
                fn (\DateTimeImmutable $f, \DateTimeImmutable $t) => $this->pageViewRepo->countUniqueVisitorsByPeriodForPage($selectedPage, $f, $t),
            );
            $selectedDaily = $this->pageViewRepo->countByDayForPage($selectedPage, $dateRange->from, $dateRange->to);
            $selectedReferrers = $this->pageViewRepo->findTopReferrersForPage($selectedPage, $dateRange->from, $dateRange->to, 5);

            $selectedDetail = [
                'pageUrl' => $selectedPage,
                'views' => $selectedViews,
                'visitors' => $selectedVisitors,
                'daily' => $selectedDaily,
                'referrers' => $selectedReferrers,
            ];
        }

        $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/pages.html.twig', [
            'from' => $dateRange->from->format('Y-m-d'),
            'to' => $dateRange->to->format('Y-m-d'),
            'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
            'active_nav' => 'pages',
            'pages' => $pages,
            'totalPages' => $totalPages,
            'selectedDetail' => $selectedDetail,
            'search' => $searchTerm ?? '',
        ]);

        return new Response($html);
    }

    #[Route(path: '/events', name: 'cookieless_analytics_dashboard_events_view', methods: ['GET'])]
    public function eventsView(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $dateRange = $this->dateRangeResolver->resolve(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $events = $this->eventRepo->findTopEvents($dateRange->from, $dateRange->to, 50);
        $totalEvents = $this->eventRepo->countByPeriod($dateRange->from, $dateRange->to);
        $distinctTypes = $this->eventRepo->countDistinctTypes($dateRange->from, $dateRange->to);
        $uniqueActors = $this->eventRepo->countUniqueActors($dateRange->from, $dateRange->to);
        $topEventName = $events[0]['name'] ?? null;

        $selectedDetail = null;
        if ($topEventName !== null) {
            $selectedDaily = $this->eventRepo->countByDayForEvent($topEventName, $dateRange->from, $dateRange->to);
            $selectedValues = $this->eventRepo->findValueBreakdown($topEventName, $dateRange->from, $dateRange->to, 10);
            $selectedPages = $this->eventRepo->findTopPagesForEvent($topEventName, $dateRange->from, $dateRange->to, 5);

            $selectedDetail = [
                'name' => $topEventName,
                'occurrences' => (int) $events[0]['occurrences'],
                'distinctValues' => (int) $events[0]['distinctValues'],
                'daily' => $selectedDaily,
                'values' => $selectedValues,
                'pages' => $selectedPages,
            ];
        }

        $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/events.html.twig', [
            'from' => $dateRange->from->format('Y-m-d'),
            'to' => $dateRange->to->format('Y-m-d'),
            'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
            'active_nav' => 'events',
            'events' => $events,
            'totalEvents' => $totalEvents,
            'distinctTypes' => $distinctTypes,
            'uniqueActors' => $uniqueActors,
            'topEventName' => $topEventName,
            'selectedDetail' => $selectedDetail,
        ]);

        return new Response($html);
    }

    #[Route(path: '/trends', name: 'cookieless_analytics_dashboard_trends_view', methods: ['GET'])]
    public function trendsView(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $dateRange = $this->dateRangeResolver->resolve(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        if ($redirect = $this->redirectIfDatesNormalized($request, $dateRange)) {
            return $redirect;
        }

        $daily = $this->pageViewRepo->countByDay($dateRange->from, $dateRange->to);
        $dates = array_map(fn (array $row) => $row['date'], $daily);
        $views = array_map(fn (array $row) => (int) $row['count'], $daily);
        $visitors = array_map(fn (array $row) => (int) $row['unique'], $daily);

        $prevDaily = $this->pageViewRepo->countByDay($dateRange->comparisonFrom, $dateRange->comparisonTo);
        $prevViews = array_map(fn (array $row) => (int) $row['count'], $prevDaily);

        $stats = $this->trendsStats->compute($daily);

        $pageViewsComparison = $this->periodComparer->compare($dateRange, $this->pageViewRepo->countByPeriod(...));
        $visitorsComparison = $this->periodComparer->compare($dateRange, $this->pageViewRepo->countUniqueVisitorsByPeriod(...));
        $eventsComparison = $this->periodComparer->compare($dateRange, $this->eventRepo->countByPeriod(...));
        $pagesPerVisitorComparison = $this->periodComparer->comparePagesPerVisitor($pageViewsComparison, $visitorsComparison);

        $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/trends.html.twig', [
            'from' => $dateRange->from->format('Y-m-d'),
            'to' => $dateRange->to->format('Y-m-d'),
            'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
            'active_nav' => 'trends',
            'dates' => json_encode($dates),
            'views' => json_encode($views),
            'visitors' => json_encode($visitors),
            'prevViews' => json_encode($prevViews),
            'peakDay' => $stats['peakDay'],
            'lowDay' => $stats['lowDay'],
            'dailyAvgViews' => $stats['dailyAvgViews'],
            'dailyAvgVisitors' => $stats['dailyAvgVisitors'],
            'weekdayAvg' => $stats['weekdayAvg'],
            'weekendAvg' => $stats['weekendAvg'],
            'pageViewsComparison' => $pageViewsComparison,
            'visitorsComparison' => $visitorsComparison,
            'eventsComparison' => $eventsComparison,
            'pagesPerVisitorComparison' => $pagesPerVisitorComparison,
        ]);

        return new Response($html);
    }

    private function redirectIfDatesNormalized(Request $request, DateRange $dateRange): ?RedirectResponse
    {
        $inputFrom = $request->query->getString('from');
        $inputTo = $request->query->getString('to');
        $resolvedFrom = $dateRange->from->format('Y-m-d');
        $resolvedTo = $dateRange->to->format('Y-m-d');

        if ($inputFrom !== '' && $inputTo !== '' && ($inputFrom !== $resolvedFrom || $inputTo !== $resolvedTo)) {
            $url = $request->getPathInfo() . '?' . http_build_query(['from' => $resolvedFrom, 'to' => $resolvedTo]);

            return new RedirectResponse($url, 302);
        }

        return null;
    }

    private function denyAccessUnlessGranted(): void
    {
        if ($this->authorizationChecker === null) {
            return;
        }

        if (!$this->authorizationChecker->isGranted($this->dashboardRole)) {
            throw new AccessDeniedException();
        }
    }
}
