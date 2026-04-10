# Custom Event Tracking — Design Spec

## Overview

Add custom event tracking to the cookieless analytics bundle. Users annotate HTML elements with `data-ca-event` (and optionally `data-ca-value`) attributes. The inline JS script listens for clicks, sends a beacon to a new `/event` endpoint, and the server persists an `AnalyticsEvent` entity with the same daily fingerprint used for page views.

## Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Endpoint | Separate `POST /ca/event` | Different payload shape from page views, keeps controllers focused |
| JS listener | Click only, no form submit | YAGNI — forms can be tracked via submit button `data-ca-event` |
| Element detection | `closest('[data-ca-event]')` | Handles clicks on child elements (e.g. `<span>` inside `<a data-ca-event>`) |
| Navigation timing | Trust sendBeacon | Designed for pre-navigation beacons, no artificial delay |
| Entity name | `AnalyticsEvent` | Avoids collision with Symfony's `Event` class |
| Value sanitization | No | `value` is arbitrary context (event title, file name), not a URL |
| pageUrl sanitization | Yes | Reuse existing `UrlSanitizer` to strip sensitive query params |

## Entity: AnalyticsEvent

Location: `src/Entity/AnalyticsEvent.php`

Table: `ca_analytics_event`

| Field | Type | Notes |
|---|---|---|
| `id` | `integer` (auto-increment) | Primary key |
| `fingerprint` | `string(64)` | Same daily hash as PageView, indexed |
| `name` | `string(255)` | Event name from `data-ca-event` |
| `value` | `string(2048)`, nullable | Optional context from `data-ca-value` |
| `pageUrl` | `string(2048)` | Sanitized URL of the page where event occurred |
| `recordedAt` | `DateTimeImmutable` | When the event was recorded |

Indexes:
- `idx_event_fingerprint` on `fingerprint`
- `idx_event_recorded_at` on `recordedAt`
- `idx_event_name` on `name` — for "top events" queries

Construction via static factory: `AnalyticsEvent::create(fingerprint, name, value, pageUrl, recordedAt)`.

## Controller: EventController

Location: `src/Controller/EventController.php`

Route: `POST /event` (prefix `/ca` applied via route import)

Flow:
1. Read JSON body (`name`, `value`, `pageUrl`)
2. Validate: `name` is required, non-empty, string → `400` if missing
3. Validate: `pageUrl` is required, non-empty, string → `400` if missing
4. Call `UrlSanitizer` on `pageUrl`
5. Call `FingerprintGenerator` with IP, User-Agent, current date
6. Create `AnalyticsEvent` via factory method
7. Persist + flush
8. Return `204 No Content`

Edge cases:
- Empty or malformed JSON body → `400`
- Missing `name` or `pageUrl` → `400`
- Empty `value` → store as `null`
- No auth, no CSRF (beacon API)

## Inline JS Script Changes

Location: `src/Twig/CookielessAnalyticsExtension.php`

Current script sends a page view beacon on `DOMContentLoaded`. Add a click event listener on `document`:

- On click, check if target or any ancestor has `data-ca-event` via `element.closest('[data-ca-event]')`
- If found, send `navigator.sendBeacon()` to `{prefix}/event` with payload:
  ```json
  {
    "name": "<data-ca-event value>",
    "value": "<data-ca-value value or null>",
    "pageUrl": "<location.pathname + location.search>"
  }
  ```
- Use Blob with `application/json` content type, same as page view beacon

The endpoint base is derived from the same `$collectUrl` constructor parameter.

## Bundle Configuration Changes

Location: `src/CookielessAnalyticsBundle.php`

- Register `EventController` in `loadExtension()`: `$services->set(EventController::class)`
- Autowiring handles dependencies (FingerprintGenerator, UrlSanitizer, EntityManagerInterface)

Location: `config/routes.php`

- Import `EventController` route attributes with same prefix

No new config keys needed.

## File Structure

**New files:**
```
src/Entity/AnalyticsEvent.php
src/Controller/EventController.php
tests/Unit/Entity/AnalyticsEventTest.php
tests/Functional/Controller/EventControllerTest.php
```

**Modified files:**
```
src/Twig/CookielessAnalyticsExtension.php
src/CookielessAnalyticsBundle.php
config/routes.php
tests/Unit/Twig/CookielessAnalyticsExtensionTest.php
```

## Testing Strategy

### Unit Tests

- `AnalyticsEventTest` — factory method creates entity with correct values, nullable value
- `CookielessAnalyticsExtensionTest` — update existing tests to assert click listener and `/event` endpoint in rendered script

### Functional Tests

- `EventControllerTest`:
  - POST with valid payload (name + pageUrl + value) → 204 + entity persisted
  - POST with valid payload without value → 204 + null value stored
  - POST with empty value → 204 + null stored
  - Missing name → 400
  - Missing pageUrl → 400
  - Empty body → 400
  - Malformed JSON → 400
  - Strips sensitive params from pageUrl
