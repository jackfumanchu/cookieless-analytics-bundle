# Future Enhancements

## Dashboard

- [ ] **Interactive detail panes** — Pages and Events sub-pages currently pre-select the first item server-side. Clicking a row should update the detail pane dynamically (Turbo Frame or Stimulus).
- [ ] **Search** — Pages sub-page has a search bar UI but no backend filtering. Add URL search to `findTopPages()` with a `LIKE` filter.
- [ ] **Pagination** — Pages list currently shows up to 50 results. Add paginated queries and pagination UI.
- [ ] **Granularity switching** — Trends sub-page could support Daily/Weekly/Monthly bucketing (currently daily only).

## Data

- [ ] **Avg. time on page** — Not tracked. Would require session-level tracking (e.g., comparing consecutive page view timestamps for the same fingerprint).
- [ ] **Bounce rate** — Not tracked. Same session-tracking dependency.
- [ ] **Avg. duration** — Not tracked. Placeholder shown as "---" in Trends small multiples.

## Architecture

- [ ] **Controller namespace refactor** — Split `Controller/` into `Collect/`, `Dashboard/`, `Frame/` namespaces for clearer intent separation. Do when adding new features (profile, settings) that make the flat structure unwieldy.
