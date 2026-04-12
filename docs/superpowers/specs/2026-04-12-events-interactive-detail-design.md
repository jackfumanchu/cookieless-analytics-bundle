# Interactive Detail Pane (Events) — Design Spec

**Issue:** #16 — Interactive detail pane for Events sub-page
**Date:** 2026-04-12

## Summary

Port the Pages interactive detail pane pattern to Events. Clicking an event row updates the detail pane via Turbo Frame with instant row highlighting. Reuses the existing `row-select` Stimulus controller.

## Architecture

Identical to Pages (#1):
- Extract detail pane into `_event_detail.html.twig` partial wrapped in `<turbo-frame id="ca-event-detail">`
- Controller detects `Turbo-Frame: ca-event-detail` with `?selected=<eventName>`, returns only the detail partial
- Event rows get `data-action="click->row-select#select"` with `data-row-select-page-url-param="{{ event.name }}"`
- `row-select` Stimulus controller is reused as-is (already generic)
- Row highlight persists via `data-selected-url` on the detail frame + `connect()` re-apply

## No new repository methods

The detail needs `occurrences` and `distinctValues` for the selected event. Rather than adding new count methods, call `findTopEvents()` and find the matching event by name.

## Testing

- Functional test: `Turbo-Frame: ca-event-detail` with `?selected=cta_click` returns detail partial
- Functional test: unknown event name returns empty state
- Browser test: click row updates detail + highlight persists
