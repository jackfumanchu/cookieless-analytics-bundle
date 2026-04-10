# Dashboard Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a standalone analytics dashboard with 4 widgets (Overview, Top pages, Events, Trends), a date range selector, and Turbo Frame-based partial reloading.

**Architecture:** Twig templates rendered by a `DashboardController`, each widget in its own Turbo Frame loaded lazily. Repositories provide aggregated data. A Stimulus controller manages the date range selector and triggers frame reloads. uPlot renders the trends chart.

**Tech Stack:** Symfony 7.4+, Twig, Symfony UX Turbo, Stimulus, uPlot (via importmap), PostgreSQL

**Spec:** `docs/superpowers/specs/2026-04-10-dashboard-design.md`

---

## File Structure

### New files

| File | Responsibility |
|------|---------------|
| `src/Repository/PageViewRepository.php` | Aggregated queries for page views (count, unique visitors, top pages, daily stats) |
| `src/Repository/AnalyticsEventRepository.php` | Aggregated queries for events (count, top events, daily stats) |
| `src/Controller/DashboardController.php` | Serves dashboard page + 4 widget fragments |
| `src/Service/DateRangeResolver.php` | Parses and validates `from`/`to` query params, computes comparison period |
| `templates/dashboard/index.html.twig` | Main dashboard page with Turbo Frames |
| `templates/dashboard/layout.html.twig` | Default minimal HTML layout |
| `templates/dashboard/_overview.html.twig` | KPI cards fragment |
| `templates/dashboard/_top_pages.html.twig` | Top pages table fragment |
| `templates/dashboard/_events.html.twig` | Events table fragment |
| `templates/dashboard/_trends.html.twig` | Trends chart fragment |
| `assets/styles/dashboard.css` | Scoped dashboard styles |
| `assets/controllers/date-range-controller.js` | Stimulus controller for date picker |
| `assets/controllers/chart-controller.js` | Stimulus controller for uPlot |
| `tests/Unit/Service/DateRangeResolverTest.php` | Unit tests for date range parsing |
| `tests/Unit/Repository/PageViewRepositoryTest.php` | Integration tests for page view queries |
| `tests/Unit/Repository/AnalyticsEventRepositoryTest.php` | Integration tests for event queries |
| `tests/Functional/Controller/DashboardControllerTest.php` | Functional tests for dashboard HTTP responses |

### Modified files

| File | Changes |
|------|---------|
| `src/Entity/PageView.php` | Add `repositoryClass` to `#[ORM\Entity]` |
| `src/Entity/AnalyticsEvent.php` | Add `repositoryClass` to `#[ORM\Entity]` |
| `src/CookielessAnalyticsBundle.php` | Add dashboard config nodes + register new services |
| `config/routes.php` | Import DashboardController routes conditionally |
| `tests/App/Kernel.php` | Register UX Turbo bundle for tests |
| `tests/App/config/cookieless_analytics.yaml` | Add dashboard test config |
| `tests/bootstrap.php` | No changes needed (SchemaTool picks up entities automatically) |
| `composer.json` | Add `symfony/ux-turbo` dependency + `symfony/asset-mapper` |

---

### Task 1: DateRangeResolver service

A pure service that parses `from`/`to` query strings into `DateTimeImmutable` objects and computes comparison periods. No database dependency — easy to test first.

**Files:**
- Create: `src/Service/DateRangeResolver.php`
- Test: `tests/Unit/Service/DateRangeResolverTest.php`

- [ ] **Step 1: Write failing tests for DateRangeResolver**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DateRangeResolverTest extends TestCase
{
    private DateRangeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DateRangeResolver();
    }

    #[Test]
    public function resolve_with_valid_dates_returns_them(): void
    {
        $result = $this->resolver->resolve('2026-04-01', '2026-04-10');

        self::assertEquals(new \DateTimeImmutable('2026-04-01 00:00:00'), $result->from);
        self::assertEquals(new \DateTimeImmutable('2026-04-10 23:59:59'), $result->to);
    }

    #[Test]
    public function resolve_with_null_defaults_to_last_30_days(): void
    {
        $result = $this->resolver->resolve(null, null);

        $expectedTo = new \DateTimeImmutable('today 23:59:59');
        $expectedFrom = $expectedTo->modify('-29 days')->setTime(0, 0, 0);

        self::assertEquals($expectedFrom, $result->from);
        self::assertEquals($expectedTo, $result->to);
    }

    #[Test]
    public function resolve_with_invalid_date_defaults_to_last_30_days(): void
    {
        $result = $this->resolver->resolve('not-a-date', '2026-04-10');

        $expectedTo = new \DateTimeImmutable('today 23:59:59');
        $expectedFrom = $expectedTo->modify('-29 days')->setTime(0, 0, 0);

        self::assertEquals($expectedFrom, $result->from);
        self::assertEquals($expectedTo, $result->to);
    }

    #[Test]
    public function resolve_with_from_after_to_defaults_to_last_30_days(): void
    {
        $result = $this->resolver->resolve('2026-04-10', '2026-04-01');

        $expectedTo = new \DateTimeImmutable('today 23:59:59');
        $expectedFrom = $expectedTo->modify('-29 days')->setTime(0, 0, 0);

        self::assertEquals($expectedFrom, $result->from);
        self::assertEquals($expectedTo, $result->to);
    }

    #[Test]
    public function comparison_period_has_same_duration(): void
    {
        $result = $this->resolver->resolve('2026-04-01', '2026-04-10');

        // 10-day range → comparison is the 10 days before
        self::assertEquals(new \DateTimeImmutable('2026-03-22 00:00:00'), $result->comparisonFrom);
        self::assertEquals(new \DateTimeImmutable('2026-03-31 23:59:59'), $result->comparisonTo);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Service/DateRangeResolverTest.php -v`
Expected: FAIL — class `DateRangeResolver` not found

- [ ] **Step 3: Implement DateRangeResolver**

```php
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
```

**Note:** `DateRange` and `DateRangeResolver` are in the same file for now since `DateRange` is a simple value object tightly coupled to the resolver. If the file grows, split it.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Service/DateRangeResolverTest.php -v`
Expected: All 5 tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/Service/DateRangeResolver.php tests/Unit/Service/DateRangeResolverTest.php
git commit -m "feat(dashboard): add DateRangeResolver service with tests"
```

---

### Task 2: PageViewRepository

Custom Doctrine repository with aggregated query methods. These tests hit the database — same pattern as the existing functional tests.

**Files:**
- Create: `src/Repository/PageViewRepository.php`
- Create: `tests/Unit/Repository/PageViewRepositoryTest.php`
- Modify: `src/Entity/PageView.php` (add `repositoryClass`)

- [ ] **Step 1: Write failing tests for PageViewRepository**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PageViewRepositoryTest extends KernelTestCase
{
    private PageViewRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $this->em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $this->em->createQuery('DELETE FROM ' . PageView::class)->execute();
        $this->repository = $this->em->getRepository(PageView::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    private function createPageView(string $url, string $fingerprint, string $date, ?string $referrer = null): void
    {
        $pageView = PageView::create(
            fingerprint: $fingerprint,
            pageUrl: $url,
            referrer: $referrer,
            viewedAt: new \DateTimeImmutable($date),
        );
        $this->em->persist($pageView);
        $this->em->flush();
    }

    #[Test]
    public function count_by_period_returns_total_views(): void
    {
        $this->createPageView('/home', 'aaa', '2026-04-05');
        $this->createPageView('/about', 'bbb', '2026-04-06');
        $this->createPageView('/home', 'aaa', '2026-04-07');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        self::assertSame(3, $this->repository->countByPeriod($from, $to));
    }

    #[Test]
    public function count_by_period_excludes_outside_range(): void
    {
        $this->createPageView('/home', 'aaa', '2026-04-04');
        $this->createPageView('/home', 'aaa', '2026-04-05');
        $this->createPageView('/home', 'aaa', '2026-04-08');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        self::assertSame(1, $this->repository->countByPeriod($from, $to));
    }

    #[Test]
    public function count_unique_visitors_by_period(): void
    {
        $fp = str_repeat('a', 64);
        $fp2 = str_repeat('b', 64);

        $this->createPageView('/home', $fp, '2026-04-05');
        $this->createPageView('/about', $fp, '2026-04-05');
        $this->createPageView('/home', $fp2, '2026-04-06');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        self::assertSame(2, $this->repository->countUniqueVisitorsByPeriod($from, $to));
    }

    #[Test]
    public function find_top_pages_returns_sorted_results(): void
    {
        $fp = str_repeat('a', 64);
        $fp2 = str_repeat('b', 64);

        $this->createPageView('/about', $fp, '2026-04-05');
        $this->createPageView('/home', $fp, '2026-04-05');
        $this->createPageView('/home', $fp2, '2026-04-06');
        $this->createPageView('/home', $fp, '2026-04-06');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        $result = $this->repository->findTopPages($from, $to, 10);

        self::assertCount(2, $result);
        self::assertSame('/home', $result[0]['pageUrl']);
        self::assertSame(3, (int) $result[0]['views']);
        self::assertSame(2, (int) $result[0]['uniqueVisitors']);
        self::assertSame('/about', $result[1]['pageUrl']);
        self::assertSame(1, (int) $result[1]['views']);
    }

    #[Test]
    public function count_by_day_returns_daily_breakdown(): void
    {
        $fp = str_repeat('a', 64);
        $fp2 = str_repeat('b', 64);

        $this->createPageView('/home', $fp, '2026-04-05 10:00:00');
        $this->createPageView('/home', $fp2, '2026-04-05 11:00:00');
        $this->createPageView('/home', $fp, '2026-04-06 10:00:00');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-06 23:59:59');

        $result = $this->repository->countByDay($from, $to);

        self::assertCount(2, $result);
        self::assertSame('2026-04-05', $result[0]['date']);
        self::assertSame(2, (int) $result[0]['count']);
        self::assertSame(2, (int) $result[0]['unique']);
        self::assertSame('2026-04-06', $result[1]['date']);
        self::assertSame(1, (int) $result[1]['count']);
        self::assertSame(1, (int) $result[1]['unique']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php -v`
Expected: FAIL — class `PageViewRepository` not found

- [ ] **Step 3: Update PageView entity to reference the repository**

In `src/Entity/PageView.php`, change:
```php
#[ORM\Entity]
```
to:
```php
#[ORM\Entity(repositoryClass: \Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository::class)]
```

- [ ] **Step 4: Implement PageViewRepository**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;

/**
 * @extends ServiceEntityRepository<PageView>
 */
class PageViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageView::class);
    }

    public function countByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.viewedAt >= :from')
            ->andWhere('p.viewedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUniqueVisitorsByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.fingerprint)')
            ->where('p.viewedAt >= :from')
            ->andWhere('p.viewedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{pageUrl: string, views: int, uniqueVisitors: int}>
     */
    public function findTopPages(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.pageUrl, COUNT(p.id) AS views, COUNT(DISTINCT p.fingerprint) AS uniqueVisitors')
            ->where('p.viewedAt >= :from')
            ->andWhere('p.viewedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('p.pageUrl')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{date: string, count: int, unique: int}>
     */
    public function countByDay(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                TO_CHAR(viewed_at, 'YYYY-MM-DD') AS date,
                COUNT(*) AS count,
                COUNT(DISTINCT fingerprint) AS unique
            FROM ca_page_view
            WHERE viewed_at >= :from AND viewed_at <= :to
            GROUP BY date
            ORDER BY date ASC
        SQL;

        return $conn->executeQuery($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();
    }
}
```

**Note:** `countByDay` uses native SQL because DQL doesn't support `TO_CHAR` (PostgreSQL date formatting). This is acceptable since the project already targets PostgreSQL exclusively.

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php -v`
Expected: All 5 tests PASS

- [ ] **Step 6: Commit**

```bash
git add src/Repository/PageViewRepository.php src/Entity/PageView.php tests/Unit/Repository/PageViewRepositoryTest.php
git commit -m "feat(dashboard): add PageViewRepository with aggregate queries"
```

---

### Task 3: AnalyticsEventRepository

Same pattern as Task 2 but for events.

**Files:**
- Create: `src/Repository/AnalyticsEventRepository.php`
- Create: `tests/Unit/Repository/AnalyticsEventRepositoryTest.php`
- Modify: `src/Entity/AnalyticsEvent.php` (add `repositoryClass`)

- [ ] **Step 1: Write failing tests for AnalyticsEventRepository**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AnalyticsEventRepositoryTest extends KernelTestCase
{
    private AnalyticsEventRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $this->em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $this->em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();
        $this->repository = $this->em->getRepository(AnalyticsEvent::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    private function createEvent(string $name, string $date, ?string $value = null): void
    {
        $event = AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: $name,
            value: $value,
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable($date),
        );
        $this->em->persist($event);
        $this->em->flush();
    }

    #[Test]
    public function count_by_period_returns_total_events(): void
    {
        $this->createEvent('click-cta', '2026-04-05');
        $this->createEvent('click-cta', '2026-04-06');
        $this->createEvent('signup', '2026-04-07');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        self::assertSame(3, $this->repository->countByPeriod($from, $to));
    }

    #[Test]
    public function find_top_events_returns_sorted_with_distinct_values(): void
    {
        $this->createEvent('click-cta', '2026-04-05', 'hero-button');
        $this->createEvent('click-cta', '2026-04-05', 'footer-button');
        $this->createEvent('click-cta', '2026-04-06', 'hero-button');
        $this->createEvent('signup', '2026-04-06', null);

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-07 23:59:59');

        $result = $this->repository->findTopEvents($from, $to, 10);

        self::assertCount(2, $result);
        self::assertSame('click-cta', $result[0]['name']);
        self::assertSame(3, (int) $result[0]['occurrences']);
        self::assertSame(2, (int) $result[0]['distinctValues']);
        self::assertSame('signup', $result[1]['name']);
        self::assertSame(1, (int) $result[1]['occurrences']);
    }

    #[Test]
    public function count_by_day_returns_daily_breakdown(): void
    {
        $this->createEvent('click-cta', '2026-04-05 10:00:00');
        $this->createEvent('signup', '2026-04-05 11:00:00');
        $this->createEvent('click-cta', '2026-04-06 10:00:00');

        $from = new \DateTimeImmutable('2026-04-05 00:00:00');
        $to = new \DateTimeImmutable('2026-04-06 23:59:59');

        $result = $this->repository->countByDay($from, $to);

        self::assertCount(2, $result);
        self::assertSame('2026-04-05', $result[0]['date']);
        self::assertSame(2, (int) $result[0]['count']);
        self::assertSame('2026-04-06', $result[1]['date']);
        self::assertSame(1, (int) $result[1]['count']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Repository/AnalyticsEventRepositoryTest.php -v`
Expected: FAIL — class `AnalyticsEventRepository` not found

- [ ] **Step 3: Update AnalyticsEvent entity to reference the repository**

In `src/Entity/AnalyticsEvent.php`, change:
```php
#[ORM\Entity]
```
to:
```php
#[ORM\Entity(repositoryClass: \Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository::class)]
```

- [ ] **Step 4: Implement AnalyticsEventRepository**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;

/**
 * @extends ServiceEntityRepository<AnalyticsEvent>
 */
class AnalyticsEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsEvent::class);
    }

    public function countByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.recordedAt >= :from')
            ->andWhere('e.recordedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{name: string, occurrences: int, distinctValues: int}>
     */
    public function findTopEvents(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.name, COUNT(e.id) AS occurrences, COUNT(DISTINCT e.value) AS distinctValues')
            ->where('e.recordedAt >= :from')
            ->andWhere('e.recordedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('e.name')
            ->orderBy('occurrences', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function countByDay(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                TO_CHAR(recorded_at, 'YYYY-MM-DD') AS date,
                COUNT(*) AS count
            FROM ca_analytics_event
            WHERE recorded_at >= :from AND recorded_at <= :to
            GROUP BY date
            ORDER BY date ASC
        SQL;

        return $conn->executeQuery($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Repository/AnalyticsEventRepositoryTest.php -v`
Expected: All 3 tests PASS

- [ ] **Step 6: Commit**

```bash
git add src/Repository/AnalyticsEventRepository.php src/Entity/AnalyticsEvent.php tests/Unit/Repository/AnalyticsEventRepositoryTest.php
git commit -m "feat(dashboard): add AnalyticsEventRepository with aggregate queries"
```

---

### Task 4: Bundle configuration for dashboard

Extend the existing `CookielessAnalyticsBundle` to add dashboard config nodes and register new services.

**Files:**
- Modify: `src/CookielessAnalyticsBundle.php`
- Modify: `tests/App/config/cookieless_analytics.yaml`

- [ ] **Step 1: Write failing test for new config**

Add a test that verifies the dashboard config is loaded. Create `tests/Unit/CookielessAnalyticsBundleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CookielessAnalyticsBundleTest extends KernelTestCase
{
    #[Test]
    public function dashboard_parameters_are_set(): void
    {
        $kernel = static::bootKernel();
        $container = $kernel->getContainer();

        self::assertTrue($container->hasParameter('cookieless_analytics.dashboard_enabled'));
        self::assertTrue($container->getParameter('cookieless_analytics.dashboard_enabled'));
        self::assertSame('/analytics', $container->getParameter('cookieless_analytics.dashboard_prefix'));
        self::assertSame('ROLE_ANALYTICS', $container->getParameter('cookieless_analytics.dashboard_role'));
        self::assertNull($container->getParameter('cookieless_analytics.dashboard_layout'));

        static::ensureKernelShutdown();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/CookielessAnalyticsBundleTest.php -v`
Expected: FAIL — parameter `cookieless_analytics.dashboard_enabled` not found

- [ ] **Step 3: Add dashboard config nodes to the bundle**

In `src/CookielessAnalyticsBundle.php`, inside the `configure()` method, add after the `exclude_paths` node (before the final `->end()`):

```php
            ->booleanNode('dashboard_enabled')
            ->defaultTrue()
            ->end()
            ->scalarNode('dashboard_prefix')
            ->defaultValue('/analytics')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('dashboard_role')
            ->defaultValue('ROLE_ANALYTICS')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('dashboard_layout')
            ->defaultNull()
            ->end()
```

Update the `$config` docblock type hint:

```php
/** @param array{collect_prefix: string, strip_query_params: list<string>, exclude_paths: list<string>, dashboard_enabled: bool, dashboard_prefix: string, dashboard_role: string, dashboard_layout: ?string} $config */
```

Add in `loadExtension()`, after existing `setParameter` calls:

```php
        $builder->setParameter('cookieless_analytics.dashboard_enabled', $config['dashboard_enabled']);
        $builder->setParameter('cookieless_analytics.dashboard_prefix', $config['dashboard_prefix']);
        $builder->setParameter('cookieless_analytics.dashboard_role', $config['dashboard_role']);
        $builder->setParameter('cookieless_analytics.dashboard_layout', $config['dashboard_layout']);
```

- [ ] **Step 4: Update test config**

In `tests/App/config/cookieless_analytics.yaml`, add:

```yaml
    dashboard_enabled: true
    dashboard_prefix: '/analytics'
    dashboard_role: 'ROLE_ANALYTICS'
    dashboard_layout: ~
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/CookielessAnalyticsBundleTest.php -v`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/CookielessAnalyticsBundle.php tests/App/config/cookieless_analytics.yaml tests/Unit/CookielessAnalyticsBundleTest.php
git commit -m "feat(dashboard): add dashboard configuration nodes to bundle"
```

---

### Task 5: DashboardController with index action

Create the controller with the main page action. Widget fragment actions will be added in subsequent tasks.

**Files:**
- Create: `src/Controller/DashboardController.php`
- Create: `tests/Functional/Controller/DashboardControllerTest.php`
- Modify: `config/routes.php`
- Modify: `src/CookielessAnalyticsBundle.php` (register controller + services)

- [ ] **Step 1: Write failing test for dashboard index**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    #[Test]
    public function index_returns_200_with_dashboard_content(): void
    {
        $client = static::createClient();

        $client->request('GET', '/analytics');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('turbo-frame#ca-overview');
        self::assertSelectorExists('turbo-frame#ca-top-pages');
        self::assertSelectorExists('turbo-frame#ca-events');
        self::assertSelectorExists('turbo-frame#ca-trends');
    }

    #[Test]
    public function index_contains_date_range_selector(): void
    {
        $client = static::createClient();

        $client->request('GET', '/analytics');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('[data-controller="date-range"]');
        self::assertSelectorExists('input[name="from"]');
        self::assertSelectorExists('input[name="to"]');
    }
}
```

**Note:** These tests don't check role-based access yet because the test kernel has no security firewall configured. We'll add access control tests in Task 10 after ensuring the basic page renders.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php -v`
Expected: FAIL — 404 Not Found (no route)

- [ ] **Step 3: Create the DashboardController**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Controller;

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
        private readonly AuthorizationCheckerInterface $authorizationChecker,
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

    private function denyAccessUnlessGranted(): void
    {
        if (!$this->authorizationChecker->isGranted($this->dashboardRole)) {
            throw new AccessDeniedException();
        }
    }
}
```

- [ ] **Step 4: Create the layout template**

```twig
{# templates/dashboard/layout.html.twig #}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics</title>
    {% block stylesheets %}{% endblock %}
</head>
<body>
    {% block body %}{% endblock %}
    {% block javascripts %}{% endblock %}
</body>
</html>
```

- [ ] **Step 5: Create the index template**

```twig
{# templates/dashboard/index.html.twig #}
{% extends layout %}

{% block body %}
<div class="ca-dashboard">
    <header class="ca-dashboard__header">
        <h1>Analytics</h1>
        <div data-controller="date-range" data-date-range-from-value="{{ from }}" data-date-range-to-value="{{ to }}">
            <div class="ca-dashboard__shortcuts">
                <button type="button" data-date-range-target="shortcut" data-period="today">Aujourd'hui</button>
                <button type="button" data-date-range-target="shortcut" data-period="7days">7 jours</button>
                <button type="button" data-date-range-target="shortcut" data-period="30days">30 jours</button>
                <button type="button" data-date-range-target="shortcut" data-period="month">Ce mois-ci</button>
            </div>
            <div class="ca-dashboard__dates">
                <input type="date" name="from" value="{{ from }}" data-date-range-target="fromInput">
                <input type="date" name="to" value="{{ to }}" data-date-range-target="toInput">
                <button type="button" data-action="date-range#apply">Appliquer</button>
            </div>
        </div>
    </header>

    <section class="ca-dashboard__kpi">
        <turbo-frame id="ca-overview" src="{{ path('cookieless_analytics_dashboard_overview', {from: from, to: to}) }}" loading="lazy">
            <p>Chargement...</p>
        </turbo-frame>
    </section>

    <section class="ca-dashboard__trends">
        <turbo-frame id="ca-trends" src="{{ path('cookieless_analytics_dashboard_trends', {from: from, to: to}) }}" loading="lazy">
            <p>Chargement...</p>
        </turbo-frame>
    </section>

    <section class="ca-dashboard__tables">
        <div class="ca-dashboard__table-col">
            <turbo-frame id="ca-top-pages" src="{{ path('cookieless_analytics_dashboard_top_pages', {from: from, to: to}) }}" loading="lazy">
                <p>Chargement...</p>
            </turbo-frame>
        </div>
        <div class="ca-dashboard__table-col">
            <turbo-frame id="ca-events" src="{{ path('cookieless_analytics_dashboard_events', {from: from, to: to}) }}" loading="lazy">
                <p>Chargement...</p>
            </turbo-frame>
        </div>
    </section>
</div>
{% endblock %}
```

- [ ] **Step 6: Register controller and templates in the bundle**

In `src/CookielessAnalyticsBundle.php`, in `loadExtension()`, add:

```php
        $services->set(DateRangeResolver::class);

        if ($config['dashboard_enabled']) {
            $services->set(DashboardController::class)
                ->arg('$dashboardRole', $config['dashboard_role'])
                ->arg('$dashboardLayout', $config['dashboard_layout']);
        }
```

Add the import at the top:
```php
use Jackfumanchu\CookielessAnalyticsBundle\Controller\DashboardController;
use Jackfumanchu\CookielessAnalyticsBundle\Service\DateRangeResolver;
```

Also add the Twig template path. In `loadExtension()`:
```php
        $container->extension('twig', [
            'paths' => [
                dirname(__DIR__) . '/templates' => 'CookielessAnalytics',
            ],
        ]);
```

- [ ] **Step 7: Register dashboard routes**

In `config/routes.php`, add:

```php
    $routes->import(DashboardController::class, 'attribute')
        ->prefix('%cookieless_analytics.dashboard_prefix%');
```

With the import:
```php
use Jackfumanchu\CookielessAnalyticsBundle\Controller\DashboardController;
```

- [ ] **Step 8: Create the templates directory and files**

Create directory `templates/dashboard/` at the project root. Write the layout and index templates from steps 4 and 5.

- [ ] **Step 9: Update test routes config**

In `tests/App/config/routes.php`, the routes file already imports the bundle routes. The test kernel should auto-discover the new dashboard routes since they come from the same `config/routes.php`.

If not, add in `tests/App/config/routes.php`:
```php
use Jackfumanchu\CookielessAnalyticsBundle\Controller\DashboardController;

// ... existing imports

$routes->import(DashboardController::class, 'attribute')
    ->prefix('/analytics');
```

- [ ] **Step 10: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php -v`
Expected: All 2 tests PASS

**Troubleshooting:** If tests fail with "route not found", check that the test kernel's `config/routes.php` imports `DashboardController`. If tests fail with "template not found", verify the Twig paths config in `loadExtension()`.

- [ ] **Step 11: Run all existing tests to check for regressions**

Run: `vendor/bin/phpunit -v`
Expected: All tests PASS (existing + new)

- [ ] **Step 12: Commit**

```bash
git add src/Controller/DashboardController.php config/routes.php src/CookielessAnalyticsBundle.php templates/ tests/Functional/Controller/DashboardControllerTest.php tests/App/config/
git commit -m "feat(dashboard): add DashboardController with index page and Turbo Frames"
```

---

### Task 6: Overview widget (KPI cards fragment)

Add the `overview()` action to `DashboardController` and its template.

**Files:**
- Modify: `src/Controller/DashboardController.php`
- Create: `templates/dashboard/_overview.html.twig`
- Modify: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Write failing test for overview fragment**

Add to `DashboardControllerTest.php`:

```php
    #[Test]
    public function overview_returns_kpi_cards(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Seed data in the current period
        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('b', 64),
            pageUrl: '/about',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'click-cta',
            value: null,
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/overview?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('2', $content); // 2 page views
        self::assertStringContainsString('ca-overview', $content);
    }
```

Add the necessary imports at the top of the test file:
```php
use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
```

Add a `setUp()` method to clear data:
```php
    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . PageView::class)->execute();
        $em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();
        static::ensureKernelShutdown();
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter overview -v`
Expected: FAIL — 404 (route not found)

- [ ] **Step 3: Add overview action to DashboardController**

Add to `DashboardController.php`:

```php
    // (This initial draft is replaced below with the full version including repositories)
```

**Wait — the controller needs repositories.** Update the constructor to accept them:

```php
    public function __construct(
        private readonly Environment $twig,
        private readonly DateRangeResolver $dateRangeResolver,
        private readonly PageViewRepository $pageViewRepo,
        private readonly AnalyticsEventRepository $eventRepo,
        private readonly string $dashboardRole,
        private readonly ?string $dashboardLayout,
    ) {
    }
```

Add imports:
```php
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\AnalyticsEventRepository;
```

Then the `overview()` action:

```php
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
```

- [ ] **Step 4: Create the overview template**

```twig
{# templates/dashboard/_overview.html.twig #}
<turbo-frame id="ca-overview">
    <div class="ca-kpi-grid">
        {% for card in [
            {label: 'Pages vues', value: pageViews, prev: prevPageViews},
            {label: 'Visiteurs uniques', value: uniqueVisitors, prev: prevUniqueVisitors},
            {label: 'Événements', value: events, prev: prevEvents},
            {label: 'Pages/visiteur', value: pagesPerVisitor, prev: prevPagesPerVisitor},
        ] %}
        <div class="ca-kpi-card">
            <span class="ca-kpi-card__label">{{ card.label }}</span>
            <span class="ca-kpi-card__value">{{ card.value }}</span>
            {% if card.prev > 0 %}
                {% set change = ((card.value - card.prev) / card.prev * 100)|round(1) %}
                <span class="ca-kpi-card__change {{ change >= 0 ? 'ca-kpi-card__change--up' : 'ca-kpi-card__change--down' }}">
                    {{ change >= 0 ? '↑' : '↓' }} {{ change|abs }}%
                </span>
            {% endif %}
        </div>
        {% endfor %}
    </div>
</turbo-frame>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter overview -v`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Controller/DashboardController.php templates/dashboard/_overview.html.twig tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat(dashboard): add overview KPI widget with comparison period"
```

---

### Task 7: Top pages widget

**Files:**
- Modify: `src/Controller/DashboardController.php`
- Create: `templates/dashboard/_top_pages.html.twig`
- Modify: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Write failing test**

Add to `DashboardControllerTest.php`:

```php
    #[Test]
    public function top_pages_returns_table(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('b', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/about',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/top-pages?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('/home', $content);
        self::assertStringContainsString('/about', $content);
        self::assertStringContainsString('ca-top-pages', $content);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter top_pages -v`
Expected: FAIL — 404

- [ ] **Step 3: Add topPages action to DashboardController**

```php
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
```

- [ ] **Step 4: Create the template**

```twig
{# templates/dashboard/_top_pages.html.twig #}
<turbo-frame id="ca-top-pages">
    <h2>Top pages</h2>
    <table class="ca-table">
        <thead>
            <tr>
                <th>URL</th>
                <th>Pages vues</th>
                <th>Visiteurs uniques</th>
            </tr>
        </thead>
        <tbody>
            {% for page in pages %}
            <tr>
                <td class="ca-table__url" title="{{ page.pageUrl }}">{{ page.pageUrl }}</td>
                <td>{{ page.views }}</td>
                <td>{{ page.uniqueVisitors }}</td>
            </tr>
            {% else %}
            <tr>
                <td colspan="3">Aucune donnée pour cette période</td>
            </tr>
            {% endfor %}
        </tbody>
    </table>
</turbo-frame>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter top_pages -v`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Controller/DashboardController.php templates/dashboard/_top_pages.html.twig tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat(dashboard): add top pages widget"
```

---

### Task 8: Events widget

**Files:**
- Modify: `src/Controller/DashboardController.php`
- Create: `templates/dashboard/_events.html.twig`
- Modify: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Write failing test**

Add to `DashboardControllerTest.php`:

```php
    #[Test]
    public function events_returns_table(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'click-cta',
            value: 'hero-button',
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->persist(AnalyticsEvent::create(
            fingerprint: str_repeat('a', 64),
            name: 'click-cta',
            value: 'footer-button',
            pageUrl: '/home',
            recordedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/events?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('click-cta', $content);
        self::assertStringContainsString('ca-events', $content);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter events_returns -v`
Expected: FAIL — 404

- [ ] **Step 3: Add events action to DashboardController**

```php
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
```

- [ ] **Step 4: Create the template**

```twig
{# templates/dashboard/_events.html.twig #}
<turbo-frame id="ca-events">
    <h2>Événements</h2>
    <table class="ca-table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Occurrences</th>
                <th>Valeurs distinctes</th>
            </tr>
        </thead>
        <tbody>
            {% for event in events %}
            <tr>
                <td>{{ event.name }}</td>
                <td>{{ event.occurrences }}</td>
                <td>{{ event.distinctValues }}</td>
            </tr>
            {% else %}
            <tr>
                <td colspan="3">Aucune donnée pour cette période</td>
            </tr>
            {% endfor %}
        </tbody>
    </table>
</turbo-frame>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter events_returns -v`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Controller/DashboardController.php templates/dashboard/_events.html.twig tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat(dashboard): add events widget"
```

---

### Task 9: Trends widget

**Files:**
- Modify: `src/Controller/DashboardController.php`
- Create: `templates/dashboard/_trends.html.twig`
- Modify: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Write failing test**

Add to `DashboardControllerTest.php`:

```php
    #[Test]
    public function trends_returns_chart_container_with_data(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist(PageView::create(
            fingerprint: str_repeat('a', 64),
            pageUrl: '/home',
            referrer: null,
            viewedAt: new \DateTimeImmutable('today'),
        ));
        $em->flush();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $client->request('GET', '/analytics/trends?from=' . $today . '&to=' . $today);

        self::assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('ca-trends', $content);
        self::assertStringContainsString('data-chart-dates-value', $content);
        self::assertStringContainsString('data-chart-views-value', $content);
        self::assertStringContainsString('data-chart-visitors-value', $content);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter trends -v`
Expected: FAIL — 404

- [ ] **Step 3: Add trends action to DashboardController**

```php
    #[Route(path: '/trends', name: 'cookieless_analytics_dashboard_trends', methods: ['GET'])]
    public function trends(Request $request): Response
    {
        $this->denyAccessUnlessGranted();

        $dateRange = $this->dateRangeResolver->resolve(
            $request->query->getString('from') ?: null,
            $request->query->getString('to') ?: null,
        );

        $daily = $this->pageViewRepo->countByDay($dateRange->from, $dateRange->to);

        $dates = array_map(fn (array $row) => $row['date'], $daily);
        $views = array_map(fn (array $row) => (int) $row['count'], $daily);
        $visitors = array_map(fn (array $row) => (int) $row['unique'], $daily);

        $html = $this->twig->render('@CookielessAnalytics/dashboard/_trends.html.twig', [
            'dates' => json_encode($dates),
            'views' => json_encode($views),
            'visitors' => json_encode($visitors),
        ]);

        return new Response($html);
    }
```

- [ ] **Step 4: Create the template**

```twig
{# templates/dashboard/_trends.html.twig #}
<turbo-frame id="ca-trends">
    <h2>Tendances</h2>
    <div class="ca-chart"
         data-controller="chart"
         data-chart-dates-value="{{ dates }}"
         data-chart-views-value="{{ views }}"
         data-chart-visitors-value="{{ visitors }}">
    </div>
</turbo-frame>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter trends -v`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Controller/DashboardController.php templates/dashboard/_trends.html.twig tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat(dashboard): add trends chart widget"
```

---

### Task 10: Dashboard CSS

Create the scoped stylesheet for the dashboard.

**Files:**
- Create: `assets/styles/dashboard.css`

- [ ] **Step 1: Create the CSS file**

```css
/* Cookieless Analytics Dashboard — scoped under .ca-dashboard */

.ca-dashboard {
    font-family: system-ui, -apple-system, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.5rem;
    color: #1a1a1a;
}

/* Header */
.ca-dashboard__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.ca-dashboard__header h1 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
}

.ca-dashboard__shortcuts {
    display: flex;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}

.ca-dashboard__shortcuts button {
    padding: 0.35rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    background: #fff;
    cursor: pointer;
    font-size: 0.8125rem;
    color: #374151;
    transition: background-color 0.15s, border-color 0.15s;
}

.ca-dashboard__shortcuts button:hover {
    background: #f3f4f6;
}

.ca-dashboard__shortcuts button.active {
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
}

.ca-dashboard__dates {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.ca-dashboard__dates input[type="date"] {
    padding: 0.35rem 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.8125rem;
}

.ca-dashboard__dates button {
    padding: 0.35rem 0.75rem;
    border: 1px solid #2563eb;
    border-radius: 0.375rem;
    background: #2563eb;
    color: #fff;
    cursor: pointer;
    font-size: 0.8125rem;
}

/* KPI Grid */
.ca-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}

.ca-kpi-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1.25rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.ca-kpi-card__label {
    font-size: 0.8125rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.ca-kpi-card__value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #111827;
}

.ca-kpi-card__change {
    font-size: 0.8125rem;
    font-weight: 500;
}

.ca-kpi-card__change--up {
    color: #059669;
}

.ca-kpi-card__change--down {
    color: #dc2626;
}

/* Trends chart */
.ca-dashboard__trends {
    margin: 1.5rem 0;
}

.ca-chart {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1.25rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    min-height: 300px;
}

/* Tables */
.ca-dashboard__tables {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.ca-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.ca-table thead th {
    background: #f9fafb;
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e5e7eb;
}

.ca-table tbody td {
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    border-bottom: 1px solid #f3f4f6;
}

.ca-table tbody tr:last-child td {
    border-bottom: none;
}

.ca-table__url {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Responsive */
@media (max-width: 768px) {
    .ca-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .ca-dashboard__tables {
        grid-template-columns: 1fr;
    }

    .ca-dashboard__header {
        flex-direction: column;
    }
}
```

- [ ] **Step 2: Reference the CSS in the layout template**

Update `templates/dashboard/layout.html.twig`:

```twig
{% block stylesheets %}
    <link rel="stylesheet" href="{{ asset('bundles/cookielessanalytics/dashboard.css') }}">
{% endblock %}
```

**Note:** The CSS file will need to be published to the host app's `public/bundles/` directory. Symfony bundles do this via `assets:install`. The file should live at `public/dashboard.css` within the bundle structure. The exact asset serving strategy (AssetMapper vs traditional public assets) will be finalized during implementation. For now, create the CSS at `assets/styles/dashboard.css`.

- [ ] **Step 3: Commit**

```bash
git add assets/styles/dashboard.css templates/dashboard/layout.html.twig
git commit -m "feat(dashboard): add scoped CSS for dashboard"
```

---

### Task 11: Stimulus controllers

Create the two Stimulus controllers: one for the date range picker and one for the uPlot chart.

**Files:**
- Create: `assets/controllers/date-range-controller.js`
- Create: `assets/controllers/chart-controller.js`

- [ ] **Step 1: Create the date-range Stimulus controller**

```javascript
// assets/controllers/date-range-controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['shortcut', 'fromInput', 'toInput'];
    static values = { from: String, to: String };

    connect() {
        this.highlightActiveShortcut();
    }

    apply() {
        const from = this.fromInputTarget.value;
        const to = this.toInputTarget.value;

        if (!from || !to || from > to) {
            return;
        }

        this.updateFrames(from, to);
    }

    shortcutTargetConnected(element) {
        element.addEventListener('click', () => {
            const period = element.dataset.period;
            const { from, to } = this.computePeriod(period);
            this.fromInputTarget.value = from;
            this.toInputTarget.value = to;
            this.updateFrames(from, to);
        });
    }

    computePeriod(period) {
        const today = new Date();
        const to = this.formatDate(today);
        let from;

        switch (period) {
            case 'today':
                from = to;
                break;
            case '7days':
                from = this.formatDate(new Date(today.getTime() - 6 * 86400000));
                break;
            case '30days':
                from = this.formatDate(new Date(today.getTime() - 29 * 86400000));
                break;
            case 'month':
                from = this.formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                break;
            default:
                from = this.formatDate(new Date(today.getTime() - 29 * 86400000));
        }

        return { from, to };
    }

    updateFrames(from, to) {
        const url = new URL(window.location);
        url.searchParams.set('from', from);
        url.searchParams.set('to', to);
        window.history.replaceState({}, '', url);

        document.querySelectorAll('turbo-frame[id^="ca-"]').forEach(frame => {
            const src = new URL(frame.src || frame.getAttribute('src'), window.location.origin);
            src.searchParams.set('from', from);
            src.searchParams.set('to', to);
            frame.src = src.toString();
        });

        this.fromValue = from;
        this.toValue = to;
        this.highlightActiveShortcut();
    }

    highlightActiveShortcut() {
        const from = this.fromInputTarget.value;
        const to = this.toInputTarget.value;

        this.shortcutTargets.forEach(btn => {
            const { from: pFrom, to: pTo } = this.computePeriod(btn.dataset.period);
            btn.classList.toggle('active', pFrom === from && pTo === to);
        });
    }

    formatDate(date) {
        return date.toISOString().slice(0, 10);
    }
}
```

- [ ] **Step 2: Create the chart Stimulus controller**

```javascript
// assets/controllers/chart-controller.js
import { Controller } from '@hotwired/stimulus';
import uPlot from 'uplot';

export default class extends Controller {
    static values = {
        dates: Array,
        views: Array,
        visitors: Array,
    };

    connect() {
        this.renderChart();
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
        }
    }

    renderChart() {
        const timestamps = this.datesValue.map(d => new Date(d + 'T00:00:00').getTime() / 1000);

        const opts = {
            width: this.element.clientWidth - 40,
            height: 280,
            series: [
                { label: 'Date' },
                {
                    label: 'Pages vues',
                    stroke: '#2563eb',
                    width: 2,
                    fill: 'rgba(37, 99, 235, 0.08)',
                },
                {
                    label: 'Visiteurs uniques',
                    stroke: '#9ca3af',
                    width: 2,
                    dash: [5, 5],
                },
            ],
            axes: [
                {
                    values: (u, vals) => vals.map(v => {
                        const d = new Date(v * 1000);
                        return `${d.getDate()}/${d.getMonth() + 1}`;
                    }),
                },
                {},
            ],
        };

        const data = [timestamps, this.viewsValue, this.visitorsValue];
        this.chart = new uPlot(opts, data, this.element);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add assets/controllers/
git commit -m "feat(dashboard): add Stimulus controllers for date range and chart"
```

---

### Task 12: Dependencies and asset configuration

Update `composer.json` and configure AssetMapper for Stimulus and uPlot.

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Add symfony/ux-turbo to composer.json**

Run:
```bash
composer require symfony/ux-turbo
```

- [ ] **Step 2: Document asset setup for bundle consumers**

The bundle consumers will need to install uPlot via importmap in their app:

```bash
php bin/console importmap:require uplot
```

Add a note in the README (but not now — defer README updates to the end).

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat(dashboard): add symfony/ux-turbo dependency"
```

---

### Task 13: Full integration test

Run all tests together to verify nothing is broken.

**Files:**
- No new files — run existing test suite

- [ ] **Step 1: Run the full test suite**

Run: `vendor/bin/phpunit -v`
Expected: All tests PASS

- [ ] **Step 2: Run PHPStan**

Run: `vendor/bin/phpstan analyse -v`
Expected: No errors

- [ ] **Step 3: Run PHP-CS-Fixer**

Run: `vendor/bin/php-cs-fixer fix --dry-run --diff`
Expected: No issues (or fix them)

- [ ] **Step 4: Fix any issues found**

If PHPStan or CS-Fixer report issues, fix them and re-run.

- [ ] **Step 5: Commit any fixes**

```bash
git add -A
git commit -m "chore: fix code style and static analysis issues"
```

---

### Task 14: Update README dashboard section

Update the README to document the actual dashboard setup.

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update the dashboard section in README**

Update the "What the dashboard shows" section and add setup instructions for bundle consumers:

1. Enable the dashboard in config (enabled by default)
2. Assign `ROLE_ANALYTICS` to users who should access the dashboard
3. Optionally configure `dashboard_layout` to use the app's own base template
4. Install uPlot via importmap: `php bin/console importmap:require uplot`
5. Access at `/analytics` (or configured prefix)

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: update README with dashboard setup instructions"
```
