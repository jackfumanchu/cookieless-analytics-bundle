# Pages Search Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire up the existing search bar UI on the Pages sub-page to filter pages by URL via live, debounced search.

**Architecture:** Stimulus controller debounces input, updates a Turbo Frame `src` with `?search=` param. The existing `pagesView` controller reads the param and passes it to `findTopPages()`, which applies a `LIKE %term%` filter. Detail pane clears during search.

**Tech Stack:** PHP 8.3, Symfony 7, Doctrine ORM, Hotwire (Turbo + Stimulus), PostgreSQL, PHPUnit

---

### Task 1: Repository — add search filter to `findTopPages()`

**Files:**
- Modify: `src/Repository/PageViewRepository.php:76-89`
- Test: `tests/Unit/Repository/PageViewRepositoryTest.php`

- [ ] **Step 1: Write the failing test for search filtering**

Add to `tests/Unit/Repository/PageViewRepositoryTest.php`:

```php
#[Test]
public function find_top_pages_with_search_filters_by_url(): void
{
    $fp = str_repeat('a', 64);

    $this->createPageView('/en/blog/hello-world', $fp, '2026-04-05');
    $this->createPageView('/en/blog/second-post', $fp, '2026-04-06');
    $this->createPageView('/en/about', $fp, '2026-04-05');
    $this->createPageView('/en/contact', $fp, '2026-04-06');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    $result = $this->repository->findTopPages($from, $to, 10, 'blog');

    self::assertCount(2, $result);
    self::assertSame('/en/blog/hello-world', $result[0]['pageUrl']);
    self::assertSame('/en/blog/second-post', $result[1]['pageUrl']);
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php --filter find_top_pages_with_search_filters_by_url`

Expected: FAIL — `findTopPages()` does not accept a 4th parameter.

- [ ] **Step 3: Write the failing test for null search (existing behavior preserved)**

Add to `tests/Unit/Repository/PageViewRepositoryTest.php`:

```php
#[Test]
public function find_top_pages_without_search_returns_all(): void
{
    $fp = str_repeat('a', 64);

    $this->createPageView('/en/blog/hello-world', $fp, '2026-04-05');
    $this->createPageView('/en/about', $fp, '2026-04-05');

    $from = new \DateTimeImmutable('2026-04-05 00:00:00');
    $to = new \DateTimeImmutable('2026-04-07 23:59:59');

    $result = $this->repository->findTopPages($from, $to, 10, null);

    self::assertCount(2, $result);
}
```

- [ ] **Step 4: Implement search filter in `findTopPages()`**

In `src/Repository/PageViewRepository.php`, replace the `findTopPages` method:

```php
public function findTopPages(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10, ?string $search = null): array
{
    $qb = $this->createQueryBuilder('p')
        ->select('p.pageUrl, COUNT(p.id) AS views, COUNT(DISTINCT p.fingerprint) AS uniqueVisitors')
        ->where('p.viewedAt >= :from')
        ->andWhere('p.viewedAt <= :to')
        ->setParameter('from', $from)
        ->setParameter('to', $to);

    if ($search !== null && $search !== '') {
        $qb->andWhere('p.pageUrl LIKE :search')
            ->setParameter('search', '%' . $search . '%');
    }

    return $qb->groupBy('p.pageUrl')
        ->orderBy('views', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}
```

- [ ] **Step 5: Run both tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Repository/PageViewRepositoryTest.php --filter find_top_pages`

Expected: all `find_top_pages*` tests PASS (including the existing `find_top_pages_returns_sorted_results`).

- [ ] **Step 6: Commit**

```bash
git add src/Repository/PageViewRepository.php tests/Unit/Repository/PageViewRepositoryTest.php
git commit -m "feat(repo): add search filter to findTopPages (#2)"
```

---

### Task 2: Controller — read search param and skip detail pane

**Files:**
- Modify: `src/Controller/DashboardController.php:62-116`
- Test: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Write the failing test for search filtering**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function pages_view_with_search_filters_results(): void
{
    $client = static::createClient();
    $em = self::getContainer()->get(EntityManagerInterface::class);

    $em->persist(PageView::create(
        fingerprint: str_repeat('a', 64),
        pageUrl: '/en/blog/hello',
        referrer: null,
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->persist(PageView::create(
        fingerprint: str_repeat('b', 64),
        pageUrl: '/en/about',
        referrer: null,
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->flush();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=blog');

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    self::assertStringContainsString('/en/blog/hello', $content);
    self::assertStringNotContainsString('/en/about', $content);
}
```

- [ ] **Step 2: Write the failing test for detail pane clearing on search**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function pages_view_with_search_shows_empty_detail_pane(): void
{
    $client = static::createClient();
    $em = self::getContainer()->get(EntityManagerInterface::class);

    $em->persist(PageView::create(
        fingerprint: str_repeat('a', 64),
        pageUrl: '/en/blog/hello',
        referrer: null,
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->flush();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=blog');

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    self::assertStringContainsString('No page selected', $content);
    self::assertStringNotContainsString('detail-header', $content);
}
```

- [ ] **Step 3: Run both tests to verify they fail**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter pages_view_with_search`

Expected: FAIL — search param is not read yet, so all pages are returned and detail pane is populated.

- [ ] **Step 4: Implement search param handling in the controller**

In `src/Controller/DashboardController.php`, modify the `pagesView` method. Replace lines 67–113 with:

```php
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
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter pages_view`

Expected: all `pages_view*` tests PASS (including the existing `pages_view_returns_200_with_page_list`).

- [ ] **Step 6: Commit**

```bash
git add src/Controller/DashboardController.php tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat(controller): read search param and skip detail pane (#2)"
```

---

### Task 3: Template — Turbo Frame wrapper and search input wiring

**Files:**
- Modify: `templates/dashboard/pages/pages.html.twig`

- [ ] **Step 1: Add Turbo Frame around list pane and wire search input**

Replace the full content of `templates/dashboard/pages/pages.html.twig` with:

```twig
{% extends layout %}

{% block content %}
  {# ─── Controls Left ─── #}
  <div class="controls-left" style="margin-bottom: 20px;">
    <span class="page-headline">Pages</span>
    <span class="page-count">{{ totalPages }} page{{ totalPages != 1 ? 's' : '' }} tracked</span>
  </div>

  {# ─── Search ─── #}
  <div class="search-bar"
       data-controller="search"
       data-search-url-value="{{ path('cookieless_analytics_dashboard_pages_view') }}"
       data-search-from-value="{{ from }}"
       data-search-to-value="{{ to }}">
    <svg class="search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="4.5"/><line x1="10.2" y1="10.2" x2="14" y2="14"/></svg>
    <input class="search-input"
           type="text"
           placeholder="Search pages by URL..."
           value="{{ search }}"
           data-search-target="input"
           data-action="input->search#filter">
    <span class="search-hint" data-search-target="hint">{{ totalPages }} results</span>
  </div>

  {# ─── Two-Pane Layout ─── #}
  <div class="page-layout">

    {# ─── List Pane (inside Turbo Frame for live search) ─── #}
    <turbo-frame id="ca-pages-list">
    <div class="list-pane">
      {% if pages|length > 0 %}
      <table class="pages-table">
        <thead>
          <tr>
            <th></th>
            <th>Page</th>
            <th class="num-head">Views</th>
            <th class="num-head">Visitors</th>
          </tr>
        </thead>
        <tbody>
          {% for page in pages %}
          <tr{% if selectedDetail and selectedDetail.pageUrl == page.pageUrl %} class="selected"{% endif %}>
            <td class="rank-col">{{ '%02d'|format(loop.index) }}</td>
            <td class="url-col">{{ page.pageUrl }}</td>
            <td class="num-col">{{ page.views|number_format(0, '.', ',') }}</td>
            <td class="num-col">{{ page.uniqueVisitors|number_format(0, '.', ',') }}</td>
          </tr>
          {% endfor %}
        </tbody>
      </table>
      {% else %}
      <div class="ca-empty">{% if search %}No pages match your search{% else %}No page data for this period{% endif %}</div>
      {% endif %}
    </div>
    </turbo-frame>

    <div class="pane-divider"></div>

    {# ─── Detail Pane ─── #}
    <div class="detail-pane">
      {% if selectedDetail %}
      <div class="detail-header">
        <div class="detail-url">{{ selectedDetail.pageUrl }}</div>
        <div class="detail-subtitle">Selected &middot; Rank #1 this period</div>
      </div>

      {# KPIs #}
      <div class="detail-kpis">
        <div class="detail-kpi">
          <div class="dk-label">Views</div>
          <div class="dk-value">{{ selectedDetail.views.current|number_format(0, '.', ',') }}</div>
          {% if selectedDetail.views.changePercent is not null %}
          <div class="dk-change {{ selectedDetail.views.changePercent >= 0 ? 'up' : 'down' }}">{{ selectedDetail.views.changePercent >= 0 ? '&#9650;' : '&#9660;' }} {{ selectedDetail.views.changePercent|abs }}% vs prev.</div>
          {% endif %}
        </div>
        <div class="detail-kpi">
          <div class="dk-label">Visitors</div>
          <div class="dk-value">{{ selectedDetail.visitors.current|number_format(0, '.', ',') }}</div>
          {% if selectedDetail.visitors.changePercent is not null %}
          <div class="dk-change {{ selectedDetail.visitors.changePercent >= 0 ? 'up' : 'down' }}">{{ selectedDetail.visitors.changePercent >= 0 ? '&#9650;' : '&#9660;' }} {{ selectedDetail.visitors.changePercent|abs }}% vs prev.</div>
          {% endif %}
        </div>
        <div class="detail-kpi">
          <div class="dk-label">Avg. Time</div>
          <div class="dk-value">&mdash;</div>
        </div>
        <div class="detail-kpi">
          <div class="dk-label">Bounce</div>
          <div class="dk-value">&mdash;</div>
        </div>
      </div>

      {# Trend Chart #}
      <div class="detail-section-label">Daily Trend</div>
      <div class="detail-chart"
           data-controller="chart"
           data-chart-dates-value="{{ selectedDetail.daily|map(d => d.date)|json_encode() }}"
           data-chart-views-value="{{ selectedDetail.daily|map(d => d.count)|json_encode() }}"
           data-chart-visitors-value="{{ selectedDetail.daily|map(d => d.unique)|json_encode() }}">
      </div>

      {# Top Referrers #}
      <div class="detail-section-label">Top Referrers</div>
      {% if selectedDetail.referrers|length > 0 %}
      {% set maxReferrerCount = selectedDetail.referrers|map(r => r.visits)|reduce((carry, v) => max(carry, v), 1) %}
      <ul class="detail-ref-list">
        {% for ref in selectedDetail.referrers %}
        <li class="detail-ref-item">
          <span class="detail-ref-name">{{ ref.source }}</span>
          <div class="detail-ref-data">
            <span class="detail-ref-count">{{ ref.visits }}</span>
            <div class="detail-ref-bar"><div class="detail-ref-fill" style="width:{{ (ref.visits / maxReferrerCount * 100)|round }}%;"></div></div>
          </div>
        </li>
        {% endfor %}
      </ul>
      {% else %}
      <div class="ca-empty" style="min-height: 60px;">No referrer data</div>
      {% endif %}

      {% else %}
      <div class="ca-empty">No page selected</div>
      {% endif %}
    </div>

  </div>
{% endblock %}
```

Key changes from original:
- Search `<div>` gets `data-controller="search"` with `url`, `from`, `to` values
- Search `<input>` gets `data-search-target="input"`, `data-action="input->search#filter"`, `value="{{ search }}"`
- Results hint gets `data-search-target="hint"`
- List pane wrapped in `<turbo-frame id="ca-pages-list">`
- Empty state text changed from "No page data for this period" to "No pages match your search"

- [ ] **Step 2: Run existing functional tests to verify nothing breaks**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter pages_view`

Expected: all PASS.

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard/pages/pages.html.twig
git commit -m "feat(template): add Turbo Frame and search data attributes (#2)"
```

---

### Task 4: Stimulus — search controller with debounce

**Files:**
- Modify: `templates/dashboard/layout.html.twig:217-292` (add controller between date-range and chart)

- [ ] **Step 1: Add the search controller to the layout's inline script**

In `templates/dashboard/layout.html.twig`, insert the following between the closing of the `date-range` controller (line 217) and the `// Chart Controller` comment (line 219):

```javascript
    // Search Controller
    app.register("search", class extends Controller {
        static targets = ["input", "hint"];
        static values = { url: String, from: String, to: String };

        connect() {
            this._timeout = null;
        }

        disconnect() {
            if (this._timeout) clearTimeout(this._timeout);
        }

        filter() {
            if (this._timeout) clearTimeout(this._timeout);
            this._timeout = setTimeout(() => this._perform(), 300);
        }

        _perform() {
            const term = this.inputTarget.value.trim();
            const params = new URLSearchParams({ from: this.fromValue, to: this.toValue });
            if (term) params.set("search", term);

            const frame = document.getElementById("ca-pages-list");
            if (frame) {
                frame.src = this.urlValue + "?" + params.toString();
            }
        }
    });
```

- [ ] **Step 2: Manual browser test**

Start the dev server and verify:
1. Navigate to the Pages sub-page
2. Type "blog" in the search bar — after ~300ms the list filters to only URLs containing "blog"
3. Clear the search bar — all pages reappear
4. Detail pane shows "No page selected" while searching
5. Results count updates with filtered count
6. Date range changes still work correctly

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard/layout.html.twig
git commit -m "feat(stimulus): add search controller with debounce (#2)"
```

---

### Task 5: Turbo Frame response — return only the frame on Turbo requests

**Files:**
- Modify: `src/Controller/DashboardController.php:62-116`
- Modify: `templates/dashboard/pages/pages.html.twig`

When Turbo fetches a frame, it extracts the matching `<turbo-frame>` from the full HTML response. This works out of the box — Turbo will find `<turbo-frame id="ca-pages-list">` in the full page response and replace just that frame. No additional controller changes needed.

However, this means every search keystroke renders the full page (layout + all blocks), which is wasteful. To optimize, detect Turbo Frame requests and return only the frame:

- [ ] **Step 1: Create a standalone frame-only template**

Create `templates/dashboard/pages/_pages_list.html.twig`:

```twig
<turbo-frame id="ca-pages-list">
<div class="list-pane">
  {% if pages|length > 0 %}
  <table class="pages-table">
    <thead>
      <tr>
        <th></th>
        <th>Page</th>
        <th class="num-head">Views</th>
        <th class="num-head">Visitors</th>
      </tr>
    </thead>
    <tbody>
      {% for page in pages %}
      <tr>
        <td class="rank-col">{{ '%02d'|format(loop.index) }}</td>
        <td class="url-col">{{ page.pageUrl }}</td>
        <td class="num-col">{{ page.views|number_format(0, '.', ',') }}</td>
        <td class="num-col">{{ page.uniqueVisitors|number_format(0, '.', ',') }}</td>
      </tr>
      {% endfor %}
    </tbody>
  </table>
  {% else %}
  <div class="ca-empty">No pages match your search</div>
  {% endif %}
</div>
</turbo-frame>
```

Note: this partial is only rendered during search (Turbo Frame requests triggered by the search controller), so "No pages match your search" is always the correct empty state here.

- [ ] **Step 2: Update the controller to return the partial for Turbo Frame requests**

In `src/Controller/DashboardController.php`, add this check after building `$pages` and `$totalPages`, before the detail pane logic:

```php
        // Turbo Frame request — return only the list frame
        if ($request->headers->get('Turbo-Frame') === 'ca-pages-list') {
            $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/_pages_list.html.twig', [
                'pages' => $pages,
                'totalPages' => $totalPages,
            ]);

            return new Response($html);
        }
```

- [ ] **Step 3: Update the search controller to also update the results hint locally**

Since the hint lives outside the Turbo Frame, update it client-side. In the `_perform()` method of the search controller in `templates/dashboard/layout.html.twig`, add after the frame src update:

Replace the `_perform()` method with:

```javascript
        _perform() {
            const term = this.inputTarget.value.trim();
            const params = new URLSearchParams({ from: this.fromValue, to: this.toValue });
            if (term) params.set("search", term);

            const frame = document.getElementById("ca-pages-list");
            if (frame) {
                frame.addEventListener("turbo:frame-load", () => {
                    const rows = frame.querySelectorAll("tbody tr");
                    if (this.hasHintTarget) {
                        this.hintTarget.textContent = rows.length + " result" + (rows.length !== 1 ? "s" : "");
                    }
                }, { once: true });
                frame.src = this.urlValue + "?" + params.toString();
            }
        }
```

- [ ] **Step 4: Write a functional test for the Turbo Frame response**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function pages_view_turbo_frame_returns_only_list(): void
{
    $client = static::createClient();
    $em = self::getContainer()->get(EntityManagerInterface::class);

    $em->persist(PageView::create(
        fingerprint: str_repeat('a', 64),
        pageUrl: '/en/blog/hello',
        referrer: null,
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->flush();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&search=blog', [], [], [
        'HTTP_TURBO_FRAME' => 'ca-pages-list',
    ]);

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    self::assertStringContainsString('turbo-frame', $content);
    self::assertStringContainsString('/en/blog/hello', $content);
    self::assertStringNotContainsString('detail-pane', $content);
    self::assertStringNotContainsString('<!DOCTYPE', $content);
}
```

- [ ] **Step 5: Run all tests**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter pages_view`

Expected: all PASS.

- [ ] **Step 6: Manual browser test**

Verify:
1. Search still works — list updates, hint count updates
2. Full page load (no search) still shows detail pane with pre-selected page
3. Date range changes still work

- [ ] **Step 7: Commit**

```bash
git add src/Controller/DashboardController.php templates/dashboard/pages/_pages_list.html.twig templates/dashboard/layout.html.twig
git commit -m "feat: optimize search with Turbo Frame partial response (#2)"
```

---

### Task 6: Final integration test and cleanup

**Files:**
- Test: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Run the full test suite**

Run: `vendor/bin/phpunit`

Expected: all tests PASS across unit, functional, and browser suites.

- [ ] **Step 2: Manual end-to-end browser test**

Full scenario:
1. Load Pages sub-page — detail pane shows first page's stats
2. Type "blog" — list filters, detail pane clears to "No page selected", hint updates
3. Clear search — all pages return, detail pane shows first page again
4. Change date range — search term is preserved, results update for new range
5. Type a term that matches nothing — empty state "No pages match your search" shown

- [ ] **Step 3: Final commit if any fixes needed**

```bash
git add -A
git commit -m "fix: address integration test findings (#2)"
```

Only create this commit if fixes were needed. Skip if everything passed.
