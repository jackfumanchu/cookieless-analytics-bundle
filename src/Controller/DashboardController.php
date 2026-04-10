<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
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

        $html = $this->twig->render('@CookielessAnalytics/dashboard/index.html.twig', [
            'from' => $dateRange->from->format('Y-m-d'),
            'to' => $dateRange->to->format('Y-m-d'),
            'layout' => $this->dashboardLayout ?? '@CookielessAnalytics/dashboard/layout.html.twig',
        ]);

        return new Response($html);
    }

    #[Route(path: '/overview', name: 'cookieless_analytics_dashboard_overview', methods: ['GET'])]
    public function overview(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $dateRange = $this->dateRangeResolver->resolve(
            $request->query->getString('from') ?: null,
            $request->query->getString('to') ?: null,
        );

        $pageViews = $this->pageViewRepo->countByPeriod($dateRange->from, $dateRange->to);
        $uniqueVisitors = $this->pageViewRepo->countUniqueVisitorsByPeriod($dateRange->from, $dateRange->to);
        $events = $this->eventRepo->countByPeriod($dateRange->from, $dateRange->to);
        $pagesPerVisitor = $uniqueVisitors > 0 ? round($pageViews / $uniqueVisitors, 1) : 0;

        // Comparison period
        $prevPageViews = $this->pageViewRepo->countByPeriod($dateRange->comparisonFrom, $dateRange->comparisonTo);
        $prevUniqueVisitors = $this->pageViewRepo->countUniqueVisitorsByPeriod($dateRange->comparisonFrom, $dateRange->comparisonTo);
        $prevEvents = $this->eventRepo->countByPeriod($dateRange->comparisonFrom, $dateRange->comparisonTo);
        $prevPagesPerVisitor = $prevUniqueVisitors > 0 ? round($prevPageViews / $prevUniqueVisitors, 1) : 0;

        $html = $this->twig->render('@CookielessAnalytics/dashboard/_overview.html.twig', [
            'pageViews' => $pageViews,
            'uniqueVisitors' => $uniqueVisitors,
            'events' => $events,
            'pagesPerVisitor' => $pagesPerVisitor,
            'prevPageViews' => $prevPageViews,
            'prevUniqueVisitors' => $prevUniqueVisitors,
            'prevEvents' => $prevEvents,
            'prevPagesPerVisitor' => $prevPagesPerVisitor,
        ]);

        return new Response($html);
    }

    #[Route(path: '/trends', name: 'cookieless_analytics_dashboard_trends', methods: ['GET'])]
    public function trends(Request $request): Response
    {
        return new Response('<turbo-frame id="ca-trends"><p>Coming soon</p></turbo-frame>');
    }

    #[Route(path: '/top-pages', name: 'cookieless_analytics_dashboard_top_pages', methods: ['GET'])]
    public function topPages(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $dateRange = $this->dateRangeResolver->resolve(
            $request->query->getString('from') ?: null,
            $request->query->getString('to') ?: null,
        );

        $pages = $this->pageViewRepo->findTopPages($dateRange->from, $dateRange->to, 10);

        $html = $this->twig->render('@CookielessAnalytics/dashboard/_top_pages.html.twig', [
            'pages' => $pages,
        ]);

        return new Response($html);
    }

    #[Route(path: '/events', name: 'cookieless_analytics_dashboard_events', methods: ['GET'])]
    public function events(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $dateRange = $this->dateRangeResolver->resolve(
            $request->query->getString('from') ?: null,
            $request->query->getString('to') ?: null,
        );

        $events = $this->eventRepo->findTopEvents($dateRange->from, $dateRange->to, 10);

        $html = $this->twig->render('@CookielessAnalytics/dashboard/_events.html.twig', [
            'events' => $events,
        ]);

        return new Response($html);
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
