# CookielessAnalytics

A lightweight, self-hosted, **cookieless** analytics bundle for Symfony 7.4+.

No cookies. No consent banner. No external service. Full GDPR compliance out of the box.

Tracks page views, unique visitors, and custom events (e.g. booking clicks) using a daily-rotated
anonymous fingerprint — no personal data is ever stored.

---

## Features

- ✅ **Cookieless by design** — no consent banner required under GDPR/ePrivacy
- ✅ **Self-hosted** — all data stays on your own server
- ✅ **PostgreSQL native** — no extra database engine required
- ✅ **Unique visitors per page** — via anonymous daily fingerprint (IP + User-Agent, hashed and rotated)
- ✅ **Navigation path tracking** — sequential page visits per anonymous session
- ✅ **Custom event tracking** — track any click or interaction with a `data-` attribute
- ✅ **Standalone dashboard** — built-in analytics UI, no EasyAdmin required
- ✅ **Multi-site ready** — one bundle installation per Symfony app
- ✅ **Lightweight script** — under 1 KB injected into your pages

---

## Requirements

- PHP 8.2+
- Symfony 7.4 or 8.x
- Doctrine ORM 3.x
- PostgreSQL 12.14+

---

## Installation

```bash
composer require jackfumanchu/cookieless-analytics-bundle
```

Run the provided migration to create the required tables:

```bash
php bin/console doctrine:migrations:migrate
```

> **Note:** The bundle ships with a migration file under `migrations/`. Copy it into your project's
> migrations directory before running the command, or let the bundle auto-register it
> (see [Configuration](#configuration)).

---

## Quick Start

### 1. Enable the bundle

If you are not using Symfony Flex, register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    Jackfumanchu\CookielessAnalyticsBundle\CookielessAnalyticsBundle::class => ['all' => true],
];
```

### 2. Add the tracking script to your base template

```twig
{# templates/base.html.twig — before </head> #}
{{ cookieless_analytics_script() }}
```

This injects a minimal inline script (under 1 KB) that sends a beacon on each page load and
listens for custom event attributes.

### 3. Track a custom event (e.g. a booking button)

Add `data-ca-event` and optionally `data-ca-value` to any HTML element:

```twig
<a href="{{ path('app_booking', {slug: event.slug}) }}"
   data-ca-event="booking-click"
   data-ca-value="{{ event.title }}">
    Book now →
</a>
```

No JavaScript required on your side — the bundle's script handles everything.

---

## Configuration

Create `config/packages/cookieless_analytics.yaml`:

```yaml
cookieless_analytics:

    # Exclude paths from tracking (regex patterns)
    exclude_paths:
        - '^/admin'
        - '^/_'
        - '^/api'

    # Enable or disable the built-in dashboard
    dashboard_enabled: true

    # Route prefix for the dashboard (default: /analytics)
    dashboard_prefix: '/analytics'

    # Role required to access the dashboard
    dashboard_role: 'ROLE_ADMIN'

    # Number of days to retain raw page view records (0 = keep forever)
    retention_days: 365
```

---

## Accessing the Dashboard

Once installed and configured, visit:

```
https://your-site.com/analytics
```

Access is restricted to users with the configured role (default: `ROLE_ADMIN`).

### What the dashboard shows

| Section | Description |
|---|---|
| **Overview** | Total page views and unique visitors for the selected period |
| **Top pages** | Most visited URLs with unique visitor counts |
| **Navigation paths** | Most common page sequences (A → B → C) |
| **Events** | Custom events ranked by count, with value breakdown |
| **Trends** | Daily/weekly chart for the selected date range |

---

## How anonymization works

No cookie, no session ID, no persistent identifier is ever stored.

Each request generates a **daily fingerprint**:

```
SHA-256( client_ip + user_agent + YYYY-MM-DD )
```

This hash:
- **changes every day** — a returning visitor cannot be tracked across days
- **is not reversible** — the original IP address cannot be recovered
- **is not personal data** under GDPR — no individual can be singled out

This approach is consistent with the CNIL guidelines on cookieless audience measurement
(délibération n°2020-091).

---

## Custom Event Tracking Reference

| Attribute | Required | Description |
|---|---|---|
| `data-ca-event` | ✅ | Event name (e.g. `booking-click`, `download`, `outbound-link`) |
| `data-ca-value` | optional | Contextual value (e.g. event title, file name, URL) |

Events are sent via `navigator.sendBeacon()` — non-blocking, fires even if the user navigates away immediately.

---

## Privacy & Legal

This bundle is designed to operate **without a cookie consent banner** under the following conditions:

1. The tracking script does not set any cookie.
2. No personal data (name, email, IP address, persistent identifier) is stored.
3. Data is processed exclusively on your own server.
4. The purpose is strictly limited to anonymous audience measurement.

> **Disclaimer:** This bundle is provided as-is. You remain responsible for your own GDPR compliance.
> Consult your DPO or legal counsel if you have specific regulatory obligations.

---

## Roadmap

- [ ] CSV/JSON data export
- [ ] Bot and crawler filtering
- [ ] UTM campaign parameter tracking
- [ ] Weekly summary email report
- [ ] Symfony UX Turbo compatibility

---

## Contributing

Contributions are welcome. Please open an issue before submitting a pull request.

```bash
git clone https://github.com/jackfumanchu/cookieless-analytics-bundle.git
cd cookieless-analytics-bundle
composer install
php vendor/bin/phpunit
```

---

## License

MIT License. See [LICENSE](LICENSE) for details.
