# Exclude Paths — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add configurable path exclusion so page views and events on matching URLs are silently discarded.

**Architecture:** A `PathExcluder` service checks URLs against regex patterns from bundle config. Both `CollectController` and `EventController` call it before persisting. Returns 204 without persisting when a path is excluded.

**Tech Stack:** PHP 8.2+, Symfony 7.4+, PHPUnit 13, PHPStan level 6

**Spec:** `docs/superpowers/specs/2026-04-10-exclude-paths-design.md`

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `src/Service/PathExcluder.php` | Create | Checks URL paths against regex exclusion patterns |
| `tests/Unit/Service/PathExcluderTest.php` | Create | Unit tests for path exclusion logic |
| `src/CookielessAnalyticsBundle.php` | Modify | Add `exclude_paths` config key, register PathExcluder |
| `src/Controller/CollectController.php` | Modify | Add PathExcluder check before persisting |
| `src/Controller/EventController.php` | Modify | Add PathExcluder check before persisting |
| `tests/App/config/cookieless_analytics.yaml` | Modify | Add exclude_paths for functional tests |
| `tests/Functional/Controller/CollectControllerTest.php` | Modify | Add excluded path test |
| `tests/Functional/Controller/EventControllerTest.php` | Modify | Add excluded path test |

---

### Task 1: PathExcluder Service

**Files:**
- Create: `tests/Unit/Service/PathExcluderTest.php`
- Create: `src/Service/PathExcluder.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\PathExcluder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PathExcluderTest extends TestCase
{
    #[Test]
    public function is_excluded_matches_single_pattern(): void
    {
        $excluder = new PathExcluder(['^/admin']);

        self::assertTrue($excluder->isExcluded('/admin'));
        self::assertTrue($excluder->isExcluded('/admin/dashboard'));
    }

    #[Test]
    public function is_excluded_matches_one_of_multiple_patterns(): void
    {
        $excluder = new PathExcluder(['^/admin', '^/_', '^/api']);

        self::assertTrue($excluder->isExcluded('/admin'));
        self::assertTrue($excluder->isExcluded('/_profiler'));
        self::assertTrue($excluder->isExcluded('/api/users'));
    }

    #[Test]
    public function is_excluded_returns_false_when_no_match(): void
    {
        $excluder = new PathExcluder(['^/admin', '^/_']);

        self::assertFalse($excluder->isExcluded('/events'));
        self::assertFalse($excluder->isExcluded('/home'));
    }

    #[Test]
    public function is_excluded_returns_false_with_empty_patterns(): void
    {
        $excluder = new PathExcluder([]);

        self::assertFalse($excluder->isExcluded('/admin'));
        self::assertFalse($excluder->isExcluded('/anything'));
    }

    #[Test]
    public function is_excluded_strips_query_string_before_matching(): void
    {
        $excluder = new PathExcluder(['^/admin']);

        self::assertTrue($excluder->isExcluded('/admin?page=1&sort=name'));
    }

    #[Test]
    public function is_excluded_respects_pattern_anchoring(): void
    {
        $excluder = new PathExcluder(['^/admin']);

        self::assertTrue($excluder->isExcluded('/admin/users'));
        self::assertFalse($excluder->isExcluded('/dashboard/admin'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php vendor/bin/phpunit tests/Unit/Service/PathExcluderTest.php --bootstrap vendor/autoload.php`
Expected: FAIL — class `PathExcluder` does not exist.

- [ ] **Step 3: Write the PathExcluder service**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class PathExcluder
{
    /**
     * @param list<string> $patterns
     */
    public function __construct(
        private readonly array $patterns,
    ) {
    }

    public function isExcluded(string $url): bool
    {
        if ($this->patterns === []) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        foreach ($this->patterns as $pattern) {
            if (preg_match('#' . $pattern . '#', $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php vendor/bin/phpunit tests/Unit/Service/PathExcluderTest.php --bootstrap vendor/autoload.php`
Expected: 6 tests, 6 passed.

- [ ] **Step 5: Run PHPStan**

Run: `php -d memory_limit=512M vendor/bin/phpstan analyse src/Service/PathExcluder.php tests/Unit/Service/PathExcluderTest.php --level 6`
Expected: No errors.

- [ ] **Step 6: Commit**

```bash
git add src/Service/PathExcluder.php tests/Unit/Service/PathExcluderTest.php
git commit -m "feat: add PathExcluder service with unit tests"
```

---

### Task 2: Bundle Configuration + Controller Integration + Functional Tests

This task wires PathExcluder into the bundle config and both controllers, then adds functional tests.

**Files:**
- Modify: `src/CookielessAnalyticsBundle.php`
- Modify: `src/Controller/CollectController.php`
- Modify: `src/Controller/EventController.php`
- Modify: `tests/App/config/cookieless_analytics.yaml`
- Modify: `tests/Functional/Controller/CollectControllerTest.php`
- Modify: `tests/Functional/Controller/EventControllerTest.php`

- [ ] **Step 1: Add `exclude_paths` to bundle config**

In `src/CookielessAnalyticsBundle.php`, add the `exclude_paths` array node to the `configure()` method, after the `strip_query_params` node:

```php
            ->arrayNode('exclude_paths')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
```

Update the `@param` PHPDoc on `loadExtension()`:

```php
    /** @param array{collect_prefix: string, strip_query_params: list<string>, exclude_paths: list<string>} $config */
```

Add `PathExcluder` import at the top:

```php
use Jackfumanchu\CookielessAnalyticsBundle\Service\PathExcluder;
```

Register PathExcluder in `loadExtension()`, after the UrlSanitizer registration:

```php
        $services->set(PathExcluder::class)
            ->arg('$patterns', $config['exclude_paths']);
```

- [ ] **Step 2: Add PathExcluder to CollectController**

In `src/Controller/CollectController.php`, add the import:

```php
use Jackfumanchu\CookielessAnalyticsBundle\Service\PathExcluder;
```

Add `PathExcluder` to the constructor:

```php
    public function __construct(
        private readonly FingerprintGenerator $fingerprintGenerator,
        private readonly UrlSanitizer $urlSanitizer,
        private readonly PathExcluder $pathExcluder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }
```

Add the exclusion check after URL sanitization (after line `$url = $this->urlSanitizer->sanitize($body['url']);`):

```php
        if ($this->pathExcluder->isExcluded($url)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }
```

- [ ] **Step 3: Add PathExcluder to EventController**

In `src/Controller/EventController.php`, add the import:

```php
use Jackfumanchu\CookielessAnalyticsBundle\Service\PathExcluder;
```

Add `PathExcluder` to the constructor:

```php
    public function __construct(
        private readonly FingerprintGenerator $fingerprintGenerator,
        private readonly UrlSanitizer $urlSanitizer,
        private readonly PathExcluder $pathExcluder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }
```

Add the exclusion check after URL sanitization (after line `$pageUrl = $this->urlSanitizer->sanitize($body['pageUrl']);`):

```php
        if ($this->pathExcluder->isExcluded($pageUrl)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }
```

- [ ] **Step 4: Add exclude_paths to test config**

Update `tests/App/config/cookieless_analytics.yaml`:

```yaml
cookieless_analytics:
    collect_prefix: '/ca'
    strip_query_params:
        - token
        - password
        - key
        - secret
        - email
    exclude_paths:
        - '^/admin'
        - '^/_'
```

- [ ] **Step 5: Add functional test for CollectController**

Add this test method to `tests/Functional/Controller/CollectControllerTest.php`:

```php
    #[Test]
    public function collect_with_excluded_path_returns_204_without_persisting(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/admin/dashboard',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageViews = $em->getRepository(PageView::class)->findAll();

        self::assertCount(0, $pageViews);
    }
```

- [ ] **Step 6: Add functional test for EventController**

Add this test method to `tests/Functional/Controller/EventControllerTest.php`:

```php
    #[Test]
    public function event_with_excluded_page_url_returns_204_without_persisting(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'admin-click',
            'pageUrl' => '/admin/settings',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $events = $em->getRepository(AnalyticsEvent::class)->findAll();

        self::assertCount(0, $events);
    }
```

Add the `AnalyticsEvent` import if not already present:

```php
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
```

- [ ] **Step 7: Run the full test suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass (39 existing + 2 new = 41 total).

- [ ] **Step 8: Run PHPStan**

Run: `php -d memory_limit=512M vendor/bin/phpstan analyse --level 6`
Expected: No errors.

- [ ] **Step 9: Commit**

```bash
git add src/CookielessAnalyticsBundle.php src/Controller/CollectController.php src/Controller/EventController.php tests/App/config/cookieless_analytics.yaml tests/Functional/Controller/CollectControllerTest.php tests/Functional/Controller/EventControllerTest.php
git commit -m "feat: add exclude_paths config and integrate PathExcluder in controllers"
```

---

### Task 3: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass (41 total).

- [ ] **Step 2: Run PHPStan**

Run: `php -d memory_limit=512M vendor/bin/phpstan analyse --level 6`
Expected: No errors.

- [ ] **Step 3: Verify file changes**

New files:
```
src/Service/PathExcluder.php
tests/Unit/Service/PathExcluderTest.php
```

Modified files:
```
src/CookielessAnalyticsBundle.php
src/Controller/CollectController.php
src/Controller/EventController.php
tests/App/config/cookieless_analytics.yaml
tests/Functional/Controller/CollectControllerTest.php
tests/Functional/Controller/EventControllerTest.php
```
