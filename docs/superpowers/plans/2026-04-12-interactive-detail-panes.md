# Interactive Detail Panes (Pages) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Clicking a row in the Pages list updates the detail pane dynamically via Turbo Frame, with instant row highlighting.

**Architecture:** Extract the detail pane into a `_page_detail.html.twig` partial wrapped in a Turbo Frame. A new `row-select` Stimulus controller handles row clicks: toggles `.selected` class client-side and updates the detail frame's `src`. The controller detects `Turbo-Frame: ca-page-detail` requests and returns only the detail partial.

**Tech Stack:** PHP 8.3, Symfony 7, Doctrine ORM, Hotwire (Turbo + Stimulus), PostgreSQL, PHPUnit

---

## File Structure

| File | Action | Responsibility |
|------|--------|----------------|
| `templates/dashboard/pages/_page_detail.html.twig` | Create | Detail pane partial (KPIs, chart, referrers) wrapped in Turbo Frame |
| `templates/dashboard/pages/pages.html.twig` | Modify | Include detail partial, add row-select data attributes |
| `templates/dashboard/pages/_pages_list.html.twig` | Modify | Add row-select data attributes to rows |
| `templates/dashboard/layout.html.twig` | Modify | Register row-select Stimulus controller |
| `src/Controller/DashboardController.php` | Modify | Handle `Turbo-Frame: ca-page-detail` requests |
| `tests/Functional/Controller/DashboardControllerTest.php` | Modify | Tests for detail frame response |

---

### Task 1: Extract detail pane into partial template

**Files:**
- Create: `templates/dashboard/pages/_page_detail.html.twig`
- Modify: `templates/dashboard/pages/pages.html.twig`

- [ ] **Step 1: Create the detail pane partial**

Create `templates/dashboard/pages/_page_detail.html.twig`:

```twig
<turbo-frame id="ca-page-detail">
<div class="detail-pane">
  {% if selectedDetail %}
  <div class="detail-header">
    <div class="detail-url">{{ selectedDetail.pageUrl }}</div>
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
</turbo-frame>
```

Note: the `detail-subtitle` line ("Selected · Rank #1 this period") is removed — it was hardcoded to "#1" which is wrong when clicking other rows.

- [ ] **Step 2: Update `pages.html.twig` to use the partial**

In `templates/dashboard/pages/pages.html.twig`, replace the entire detail pane section (from `{# ─── Detail Pane ─── #}` through to the closing `</div>` of `.detail-pane`, which is everything from the `<div class="detail-pane">` line to the `{% endif %}` and closing `</div>` before the final `</div>` and `{% endblock %}`) with:

```twig
    {# ─── Detail Pane ─── #}
    {% include '@CookielessAnalytics/dashboard/pages/_page_detail.html.twig' %}
```

- [ ] **Step 3: Run existing tests to verify the refactor is safe**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter pages_view`

Expected: all PASS (no behavior change, just extraction).

- [ ] **Step 4: Commit**

```bash
git add templates/dashboard/pages/_page_detail.html.twig templates/dashboard/pages/pages.html.twig
git commit -m "refactor: extract detail pane into _page_detail.html.twig partial (#1)"
```

---

### Task 2: Controller — handle Turbo-Frame: ca-page-detail requests

**Files:**
- Modify: `src/Controller/DashboardController.php:99-116`
- Test: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Write the failing test for detail frame response**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function pages_view_turbo_frame_detail_returns_selected_page(): void
{
    $client = static::createClient();
    $em = self::getContainer()->get(EntityManagerInterface::class);

    $em->persist(PageView::create(
        fingerprint: str_repeat('a', 64),
        pageUrl: '/home',
        referrer: 'https://google.com/search',
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->persist(PageView::create(
        fingerprint: str_repeat('b', 64),
        pageUrl: '/home',
        referrer: null,
        viewedAt: new \DateTimeImmutable('today'),
    ));
    $em->flush();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&selected=%2Fhome', [], [], [
        'HTTP_TURBO_FRAME' => 'ca-page-detail',
    ]);

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    self::assertStringContainsString('turbo-frame', $content);
    self::assertStringContainsString('id="ca-page-detail"', $content);
    self::assertStringContainsString('/home', $content);
    self::assertStringContainsString('dk-value', $content);
    self::assertStringNotContainsString('pages-table', $content);
    self::assertStringNotContainsString('<!DOCTYPE', $content);
}
```

- [ ] **Step 2: Write the failing test for nonexistent page**

Add to `tests/Functional/Controller/DashboardControllerTest.php`:

```php
#[Test]
public function pages_view_turbo_frame_detail_with_unknown_page_shows_empty(): void
{
    $client = static::createClient();

    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $client->request('GET', '/analytics/pages?from=' . $today . '&to=' . $today . '&selected=%2Fnonexistent', [], [], [
        'HTTP_TURBO_FRAME' => 'ca-page-detail',
    ]);

    self::assertResponseStatusCodeSame(200);
    $content = $client->getResponse()->getContent();
    self::assertStringContainsString('ca-page-detail', $content);
    self::assertStringContainsString('No page selected', $content);
    self::assertStringNotContainsString('dk-value', $content);
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter turbo_frame_detail`

Expected: FAIL — controller doesn't handle `ca-page-detail` yet.

- [ ] **Step 4: Implement the Turbo Frame detail handler**

In `src/Controller/DashboardController.php`, in the `pagesView` method, add this block AFTER the existing `ca-pages-list` Turbo Frame check (after line 116, `return new Response($html);` and its closing `}`) and BEFORE the `// Pre-select the first page` comment:

```php
        // Turbo Frame request — return only the detail pane
        if ($request->headers->get('Turbo-Frame') === 'ca-page-detail') {
            $selected = $request->query->get('selected');
            $selectedDetail = null;

            if (is_string($selected) && $selected !== '') {
                $viewCount = $this->pageViewRepo->countByPeriodForPage($selected, $dateRange->from, $dateRange->to);

                if ($viewCount > 0) {
                    $selectedViews = $this->periodComparer->compare(
                        $dateRange,
                        fn (\DateTimeImmutable $f, \DateTimeImmutable $t) => $this->pageViewRepo->countByPeriodForPage($selected, $f, $t),
                    );
                    $selectedVisitors = $this->periodComparer->compare(
                        $dateRange,
                        fn (\DateTimeImmutable $f, \DateTimeImmutable $t) => $this->pageViewRepo->countUniqueVisitorsByPeriodForPage($selected, $f, $t),
                    );
                    $selectedDaily = $this->pageViewRepo->countByDayForPage($selected, $dateRange->from, $dateRange->to);
                    $selectedReferrers = $this->pageViewRepo->findTopReferrersForPage($selected, $dateRange->from, $dateRange->to, 5);

                    $selectedDetail = [
                        'pageUrl' => $selected,
                        'views' => $selectedViews,
                        'visitors' => $selectedVisitors,
                        'daily' => $selectedDaily,
                        'referrers' => $selectedReferrers,
                    ];
                }
            }

            $html = $this->twig->render('@CookielessAnalytics/dashboard/pages/_page_detail.html.twig', [
                'selectedDetail' => $selectedDetail,
            ]);

            return new Response($html);
        }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter "pages_view_turbo_frame_detail|pages_view"'`

Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/DashboardController.php tests/Functional/Controller/DashboardControllerTest.php
git commit -m "feat(controller): handle Turbo-Frame detail pane requests (#1)"
```

---

### Task 3: Add row-select data attributes to templates

**Files:**
- Modify: `templates/dashboard/pages/pages.html.twig`
- Modify: `templates/dashboard/pages/_pages_list.html.twig`

- [ ] **Step 1: Update the full page template table**

In `templates/dashboard/pages/pages.html.twig`, make these changes to the table inside the Turbo Frame:

Replace the `<table class="pages-table">` opening tag with:

```twig
      <table class="pages-table"
             data-controller="row-select"
             data-row-select-url-value="{{ path('cookieless_analytics_dashboard_pages_view') }}"
             data-row-select-from-value="{{ from }}"
             data-row-select-to-value="{{ to }}">
```

Replace the `<tr>` line in tbody:

```twig
          <tr{% if selectedDetail and selectedDetail.pageUrl == page.pageUrl %} class="selected"{% endif %}>
```

with:

```twig
          <tr{% if selectedDetail and selectedDetail.pageUrl == page.pageUrl %} class="selected"{% endif %}
              data-action="click->row-select#select"
              data-row-select-page-url-param="{{ page.pageUrl }}">
```

- [ ] **Step 2: Update the Turbo Frame partial table**

In `templates/dashboard/pages/_pages_list.html.twig`, make the same changes.

Replace the `<table class="pages-table">` opening tag with:

```twig
  <table class="pages-table"
         data-controller="row-select"
         data-row-select-url-value="{{ path('cookieless_analytics_dashboard_pages_view') }}"
         data-row-select-from-value="{{ from }}"
         data-row-select-to-value="{{ to }}">
```

Replace the `<tr>` line in tbody:

```twig
      <tr>
```

with:

```twig
      <tr data-action="click->row-select#select"
          data-row-select-page-url-param="{{ page.pageUrl }}">
```

- [ ] **Step 3: Run existing tests**

Run: `vendor/bin/phpunit tests/Functional/Controller/DashboardControllerTest.php --filter pages_view`

Expected: all PASS.

- [ ] **Step 4: Commit**

```bash
git add templates/dashboard/pages/pages.html.twig templates/dashboard/pages/_pages_list.html.twig
git commit -m "feat(template): add row-select data attributes to page rows (#1)"
```

---

### Task 4: Stimulus row-select controller

**Files:**
- Modify: `templates/dashboard/layout.html.twig` (insert between search and chart controllers)

- [ ] **Step 1: Add the row-select controller**

In `templates/dashboard/layout.html.twig`, insert the following between the closing of the search controller (line 247, `});`) and the `// Chart Controller (uPlot)` comment (line 249):

```javascript

    // Row-Select Controller
    app.register("row-select", class extends Controller {
        static values = { url: String, from: String, to: String };

        select(event) {
            const row = event.currentTarget;
            const pageUrl = event.params.pageUrl;
            if (!pageUrl) return;

            // Highlight clicked row
            this.element.querySelectorAll("tbody tr.selected").forEach(tr => tr.classList.remove("selected"));
            row.classList.add("selected");

            // Update detail pane via Turbo Frame
            const params = new URLSearchParams({ from: this.fromValue, to: this.toValue, selected: pageUrl });
            const frame = document.getElementById("ca-page-detail");
            if (frame) {
                frame.src = this.urlValue + "?" + params.toString();
            }
        }
    });
```

- [ ] **Step 2: Commit**

```bash
git add templates/dashboard/layout.html.twig
git commit -m "feat(stimulus): add row-select controller for detail pane (#1)"
```

---

### Task 5: Final integration test and manual verification

**Files:**
- Test: `tests/Functional/Controller/DashboardControllerTest.php`

- [ ] **Step 1: Run the full test suite**

Run: `vendor/bin/phpunit`

Expected: all tests PASS.

- [ ] **Step 2: Manual browser test**

Full scenario:
1. Load Pages sub-page — first row highlighted, detail pane shows its data
2. Click a different row — highlight moves instantly, detail pane updates with new page's KPIs, chart, and referrers
3. Click another row — same behavior, smooth transitions
4. Search for a term — list filters, detail pane stays (showing last clicked page's data)
5. Click a row in the filtered list — detail pane updates
6. Clear search — list restores, highlight gone (expected), detail shows last clicked data
7. Change page (pagination) — list updates, detail stays
8. Click row on page 2 — detail updates for that page
9. Change date range — full page reload, first page pre-selected again

- [ ] **Step 3: Final commit if any fixes needed**

Only create this commit if fixes were needed during manual testing. Skip if everything passed.
