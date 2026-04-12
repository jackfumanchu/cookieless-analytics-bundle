<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
use Jackfumanchu\CookielessAnalyticsBundle\Service\EventDetailBuilder;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PageDetailBuilder;
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
        private readonly PageDetailBuilder $pageDetailBuilder,
        private readonly EventDetailBuilder $eventDetailBuilder,
        private readonly ?AuthorizationCheckerInterface $authorizationChecker,
        private readonly string $dashboardRole,
        private readonly ?string $dashboardLayout,
    ) {}

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
        $perPage = 20;

        // Total pages tracked (unfiltered, for header)
        $totalTracked = $this->pageViewRepo->countDistinctPages($dateRange->from, $dateRange->to);

        // Count filtered distinct pages (for pagination and results hint)
        $totalDistinct = $searchTerm !== null
            ? $this->pageViewRepo->countDistinctPages($dateRange->from, $dateRange->to, $searchTerm)
            : $totalTracked;
        $totalPagesCount = max(1, (int) ceil($totalDistinct / $perPage));

        // Read and clamp page number
        $page = max(1, (int) $request->query->get('page', 1));
        $page = min($page, $totalPagesCount);
        $offset = ($page - 1) * $perPage;

        $pages = $this->pageViewRepo->findTopPages($dateRange->from, $dateRange->to, $perPage, $searchTerm, $offset);
        $totalPages = count($pages);

        // Turbo Frame request — return only the list frame
        if ($request->headers->get('Turbo-Frame') === 'ca-pages-list') {
            $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/_pages_list.html.twig', [
                'pages' => $pages,
                'totalPages' => $totalPages,
                'totalDistinct' => $totalDistinct,
                'totalTracked' => $totalTracked,
                'currentPage' => $page,
                'totalPagesCount' => $totalPagesCount,
                'perPage' => $perPage,
                'from' => $dateRange->from->format('Y-m-d'),
                'to' => $dateRange->to->format('Y-m-d'),
                'search' => $searchTerm ?? '',
                'offset' => $offset,
            ]);

            return new Response($html);
        }

        // Turbo Frame request — return only the detail pane
        if ($request->headers->get('Turbo-Frame') === 'ca-page-detail') {
            $selected = $request->query->get('selected');
            $selectedDetail = is_string($selected) && $selected !== ''
                ? $this->pageDetailBuilder->build($selected, $dateRange)
                : null;

            $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/_page_detail.html.twig', [
                'selectedDetail' => $selectedDetail,
            ]);

            return new Response($html);
        }

        // Pre-select the first page for the detail pane (only when not searching)
        $selectedPage = $searchTerm === null ? ($pages[0]['pageUrl'] ?? null) : null;
        $selectedDetail = $selectedPage !== null
            ? $this->pageDetailBuilder->build($selectedPage, $dateRange)
            : null;

        $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/pages.html.twig', [
            'from' => $dateRange->from->format('Y-m-d'),
            'to' => $dateRange->to->format('Y-m-d'),
            'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
            'active_nav' => 'pages',
            'pages' => $pages,
            'totalPages' => $totalPages,
            'totalDistinct' => $totalDistinct,
            'totalTracked' => $totalTracked,
            'selectedDetail' => $selectedDetail,
            'search' => $searchTerm ?? '',
            'currentPage' => $page,
            'totalPagesCount' => $totalPagesCount,
            'perPage' => $perPage,
            'offset' => $offset,
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

        // Turbo Frame request — return only the detail pane
        if ($request->headers->get('Turbo-Frame') === 'ca-event-detail') {
            $selected = $request->query->get('selected');
            $selectedDetail = is_string($selected) && $selected !== ''
                ? $this->eventDetailBuilder->build($selected, $dateRange, $events)
                : null;

            $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/_event_detail.html.twig', [
                'selectedDetail' => $selectedDetail,
            ]);

            return new Response($html);
        }

        $totalEvents = $this->eventRepo->countByPeriod($dateRange->from, $dateRange->to);
        $distinctTypes = $this->eventRepo->countDistinctTypes($dateRange->from, $dateRange->to);
        $uniqueActors = $this->eventRepo->countUniqueActors($dateRange->from, $dateRange->to);
        $topEventName = $events[0]['name'] ?? null;

        $selectedDetail = $topEventName !== null
            ? $this->eventDetailBuilder->build($topEventName, $dateRange, $events)
            : null;

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
            $params = $request->query->all();
            $params['from'] = $resolvedFrom;
            $params['to'] = $resolvedTo;
            $url = $request->getPathInfo() . '?' . http_build_query($params);

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
