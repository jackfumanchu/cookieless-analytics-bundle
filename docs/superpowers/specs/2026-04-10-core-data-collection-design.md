# Core Data Collection Pipeline — Design Spec

## Overview

First vertical slice of the cookieless analytics bundle: capture page view beacons from the browser, generate an anonymous daily fingerprint server-side, and persist the data. Includes the inline JS script, Twig function, controller, services, entity, and bundle configuration.

## Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Fingerprint source | Server-side only (IP + UA + date) | Simplest, most privacy-safe. No client-side entropy. |
| Beacon endpoint | `POST /{configurable_prefix}/collect` | Configurable prefix avoids route collisions with host app |
| ID strategy | Auto-increment integer | Append-heavy table, smaller indexes, no external exposure |
| URL storage | Full path + sanitized query string | Preserves UTM and meaningful params, strips sensitive ones |
| Referrer | Store all (internal + external) | Valuable for traffic source analysis |
| Sensitive param stripping | Configurable list with defaults | Every app has its own sensitive params |
| Architecture | Service layer behind thin controller | Independently testable, TDD-friendly, pragmatic flat structure |
| JS delivery | Inline script via Twig function | Zero host-app setup, no Stimulus/UX dependency |
| Fallback for sendBeacon | None | 97%+ browser support, consistent with lightweight philosophy |

## Entity: PageView

Location: `src/Entity/PageView.php`

| Field | Type | Notes |
|---|---|---|
| `id` | `integer` (auto-increment) | Primary key |
| `fingerprint` | `string(64)` | SHA-256 hex, indexed |
| `pageUrl` | `string(2048)` | Full path + sanitized query string |
| `referrer` | `string(2048)`, nullable | Full referrer URL |
| `viewedAt` | `DateTimeImmutable` | When the page view was recorded |

Indexes:
- `idx_fingerprint` on `fingerprint` — unique visitor queries
- `idx_viewed_at` on `viewedAt` — date range filtering
- `idx_page_url` on `pageUrl(255)` — top pages queries (prefix index)

Construction via static factory method: `PageView::create(fingerprint, pageUrl, referrer, viewedAt)`.

## Services

### FingerprintGenerator

Location: `src/Service/FingerprintGenerator.php`

- Input: client IP (`string`), User-Agent (`string`), date (`DateTimeImmutable`)
- Output: `string` (64-char SHA-256 hex)
- Logic: `hash('sha256', $ip . $userAgent . $date->format('Y-m-d'))`
- Pure function, no dependencies

### UrlSanitizer

Location: `src/Service/UrlSanitizer.php`

- Input: URL (`string`)
- Output: sanitized URL (`string`)
- Logic: parse query string, remove params matching the configured strip list, rebuild URL. If no query params remain, return just the path.
- Strip list injected via constructor from bundle configuration

### No PageViewPersister

Controller calls `EntityManager::persist()` + `flush()` directly. Extract a persister only if persistence logic grows (deduplication, batching).

## Controller: CollectController

Location: `src/Controller/CollectController.php`

Route: `POST /{collect_prefix}/collect` (default prefix: `/ca`)

Flow:
1. Read JSON body (`url`, `referrer`)
2. Validate: `url` is required and non-empty → `400` if missing
3. Call `UrlSanitizer` on `url` (and on `referrer` if present)
4. Call `FingerprintGenerator` with IP, User-Agent, current date
5. Create `PageView` entity via factory method
6. Persist + flush
7. Return `204 No Content`

Edge cases:
- Empty or malformed JSON body → `400`
- Missing `url` field → `400`
- Empty referrer → store as `null`
- No auth required (public endpoint)
- No CSRF (beacon API, not a form)

## Bundle Configuration

### Configuration.php

Location: `src/DependencyInjection/Configuration.php`

Config tree:

```yaml
cookieless_analytics:
    collect_prefix: '/ca'
    strip_query_params:
        - token
        - password
        - key
        - secret
        - email
```

### Extension

Location: `src/DependencyInjection/CookielessAnalyticsExtension.php`

- Reads and validates config via `Configuration.php`
- Injects `collect_prefix` into route import
- Injects `strip_query_params` into `UrlSanitizer` constructor
- Registers services and controller via `config/services.php`

## Twig Function + Inline JS Script

### Twig Extension

Location: `src/Twig/CookielessAnalyticsExtension.php`

- Registers `cookieless_analytics_script()` Twig function
- Renders an inline `<script>` tag with the beacon code
- Collect endpoint URL injected from `collect_prefix` + `/collect`

### Inline JS Behavior

- On `DOMContentLoaded`, sends `navigator.sendBeacon()` POST to collect endpoint
- JSON payload: `{ "url": <pathname + search>, "referrer": <document.referrer> }`
- Fire-and-forget, non-blocking
- No cookies, no localStorage, no sessionStorage

## File Structure

```
src/
  Controller/
    CollectController.php
  DependencyInjection/
    CookielessAnalyticsExtension.php
    Configuration.php
  Entity/
    PageView.php
  Service/
    FingerprintGenerator.php
    UrlSanitizer.php
  Twig/
    CookielessAnalyticsExtension.php
  CookielessAnalyticsBundle.php
config/
  routes.php
  services.php
```

## Testing Strategy

Following TDD (red-green-refactor) and the project's test levels definition.

### Unit Tests

- `FingerprintGeneratorTest` — deterministic hash output, same input = same hash, different date = different hash
- `UrlSanitizerTest` — strips configured params, preserves others, handles edge cases (no query string, all params stripped, encoded params)
- `PageViewTest` — factory method creates entity with correct values

### Functional Tests

- `CollectControllerTest` — POST with valid payload → 204 + entity persisted, missing URL → 400, empty body → 400, with referrer → stored, without referrer → null

### Not Needed Yet

- No integration tests — no custom repository queries at this stage
- Functional tests cover the persistence path through the real database
