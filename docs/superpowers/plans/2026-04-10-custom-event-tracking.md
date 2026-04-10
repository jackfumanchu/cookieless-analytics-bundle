# Custom Event Tracking — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add custom event tracking via `data-ca-event` HTML attributes, a new `/event` endpoint, and an `AnalyticsEvent` entity.

**Architecture:** New `EventController` at `POST /ca/event` receives event beacons from a click listener added to the existing inline JS script. The controller delegates to existing `FingerprintGenerator` and `UrlSanitizer` services, then persists an `AnalyticsEvent` entity. Follows the same patterns as the page view pipeline.

**Tech Stack:** PHP 8.2+, Symfony 7.4+, Doctrine ORM 3.x, Twig 3/4, PHPUnit 13, PHPStan level 6

**Spec:** `docs/superpowers/specs/2026-04-10-custom-event-tracking-design.md`

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `src/Entity/AnalyticsEvent.php` | Create | Doctrine entity — stores a single custom event record |
| `src/Controller/EventController.php` | Create | Thin controller — validates event beacon payload, delegates to services, persists |
| `src/Twig/CookielessAnalyticsExtension.php` | Modify | Add click listener JS for `data-ca-event` elements |
| `src/CookielessAnalyticsBundle.php` | Modify | Register EventController service |
| `config/routes.php` | Modify | Import EventController route attributes |
| `tests/Unit/Entity/AnalyticsEventTest.php` | Create | Unit tests for entity factory method |
| `tests/Functional/Controller/EventControllerTest.php` | Create | Functional tests for the event endpoint |
| `tests/Unit/Twig/CookielessAnalyticsExtensionTest.php` | Modify | Assert click listener and `/event` endpoint in rendered script |

---

### Task 1: AnalyticsEvent Entity

**Files:**
- Create: `tests/Unit/Entity/AnalyticsEventTest.php`
- Create: `src/Entity/AnalyticsEvent.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Entity;

use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AnalyticsEventTest extends TestCase
{
    #[Test]
    public function create_returns_event_with_all_fields(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-04-10 14:30:00');

        $event = AnalyticsEvent::create(
            fingerprint: 'abc123def456abc123def456abc123def456abc123def456abc123def456abcd',
            name: 'booking-click',
            value: 'Summer Festival 2026',
            pageUrl: '/events?category=music',
            recordedAt: $recordedAt,
        );

        self::assertSame('abc123def456abc123def456abc123def456abc123def456abc123def456abcd', $event->getFingerprint());
        self::assertSame('booking-click', $event->getName());
        self::assertSame('Summer Festival 2026', $event->getValue());
        self::assertSame('/events?category=music', $event->getPageUrl());
        self::assertSame($recordedAt, $event->getRecordedAt());
        self::assertNull($event->getId());
    }

    #[Test]
    public function create_accepts_null_value(): void
    {
        $event = AnalyticsEvent::create(
            fingerprint: 'abc123def456abc123def456abc123def456abc123def456abc123def456abcd',
            name: 'download',
            value: null,
            pageUrl: '/docs',
            recordedAt: new \DateTimeImmutable(),
        );

        self::assertNull($event->getValue());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php vendor/bin/phpunit tests/Unit/Entity/AnalyticsEventTest.php`
Expected: FAIL — class `AnalyticsEvent` does not exist.

- [ ] **Step 3: Write the AnalyticsEvent entity**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ca_analytics_event')]
#[ORM\Index(columns: ['fingerprint'], name: 'idx_event_fingerprint')]
#[ORM\Index(columns: ['recorded_at'], name: 'idx_event_recorded_at')]
#[ORM\Index(columns: ['name'], name: 'idx_event_name')]
class AnalyticsEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $fingerprint;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $value;

    #[ORM\Column(type: Types::STRING, length: 2048, name: 'page_url')]
    private string $pageUrl;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'recorded_at')]
    private \DateTimeImmutable $recordedAt;

    private function __construct(
        string $fingerprint,
        string $name,
        ?string $value,
        string $pageUrl,
        \DateTimeImmutable $recordedAt,
    ) {
        $this->fingerprint = $fingerprint;
        $this->name = $name;
        $this->value = $value;
        $this->pageUrl = $pageUrl;
        $this->recordedAt = $recordedAt;
    }

    public static function create(
        string $fingerprint,
        string $name,
        ?string $value,
        string $pageUrl,
        \DateTimeImmutable $recordedAt,
    ): self {
        return new self($fingerprint, $name, $value, $pageUrl, $recordedAt);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getPageUrl(): string
    {
        return $this->pageUrl;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php vendor/bin/phpunit tests/Unit/Entity/AnalyticsEventTest.php`
Expected: 2 tests, 2 passed.

- [ ] **Step 5: Run PHPStan**

Run: `php -d memory_limit=512M vendor/bin/phpstan analyse src/Entity/AnalyticsEvent.php tests/Unit/Entity/AnalyticsEventTest.php --level 6`
Expected: No errors.

- [ ] **Step 6: Commit**

```bash
git add src/Entity/AnalyticsEvent.php tests/Unit/Entity/AnalyticsEventTest.php
git commit -m "feat: add AnalyticsEvent entity with factory method and unit tests"
```

---

### Task 2: EventController + Functional Tests

**Files:**
- Create: `src/Controller/EventController.php`
- Create: `tests/Functional/Controller/EventControllerTest.php`

- [ ] **Step 1: Write the EventController**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Service\FingerprintGenerator;
use Jackfumanchu\CookielessAnalyticsBundle\Service\UrlSanitizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController
{
    public function __construct(
        private readonly FingerprintGenerator $fingerprintGenerator,
        private readonly UrlSanitizer $urlSanitizer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/event', name: 'cookieless_analytics_event', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        if (empty($body['name']) || !is_string($body['name'])) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        if (empty($body['pageUrl']) || !is_string($body['pageUrl'])) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $pageUrl = $this->urlSanitizer->sanitize($body['pageUrl']);

        $value = null;
        if (!empty($body['value']) && is_string($body['value'])) {
            $value = $body['value'];
        }

        $fingerprint = $this->fingerprintGenerator->generate(
            $request->getClientIp() ?? '0.0.0.0',
            $request->headers->get('User-Agent', ''),
            new \DateTimeImmutable(),
        );

        $event = AnalyticsEvent::create(
            fingerprint: $fingerprint,
            name: $body['name'],
            value: $value,
            pageUrl: $pageUrl,
            recordedAt: new \DateTimeImmutable(),
        );

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 2: Write the functional tests**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Functional\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function event_with_valid_payload_returns_204_and_persists(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'booking-click',
            'value' => 'Summer Festival 2026',
            'pageUrl' => '/events?category=music',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $events = $em->getRepository(AnalyticsEvent::class)->findAll();

        self::assertCount(1, $events);
        self::assertSame('booking-click', $events[0]->getName());
        self::assertSame('Summer Festival 2026', $events[0]->getValue());
        self::assertSame('/events?category=music', $events[0]->getPageUrl());
        self::assertSame(64, strlen($events[0]->getFingerprint()));
    }

    #[Test]
    public function event_without_value_stores_null(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'download',
            'pageUrl' => '/docs',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $events = $em->getRepository(AnalyticsEvent::class)->findAll();

        self::assertCount(1, $events);
        self::assertNull($events[0]->getValue());
    }

    #[Test]
    public function event_with_empty_value_stores_null(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'download',
            'value' => '',
            'pageUrl' => '/docs',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $events = $em->getRepository(AnalyticsEvent::class)->findAll();

        self::assertNull($events[0]->getValue());
    }

    #[Test]
    public function event_with_missing_name_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pageUrl' => '/docs',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function event_with_missing_page_url_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'booking-click',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function event_with_empty_body_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function event_with_malformed_json_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{invalid json');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function event_strips_sensitive_query_params_from_page_url(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'booking-click',
            'pageUrl' => '/page?token=secret123&category=music',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $events = $em->getRepository(AnalyticsEvent::class)->findAll();

        self::assertSame('/page?category=music', $events[0]->getPageUrl());
    }
}
```

- [ ] **Step 3: Run PHPStan on the controller**

Run: `php -d memory_limit=512M vendor/bin/phpstan analyse src/Controller/EventController.php --level 6`
Expected: No errors.

- [ ] **Step 4: Commit**

```bash
git add src/Controller/EventController.php tests/Functional/Controller/EventControllerTest.php
git commit -m "feat: add EventController with functional tests"
```

---

### Task 3: Bundle Wiring

Register the new controller and update routes so the functional tests can run.

**Files:**
- Modify: `src/CookielessAnalyticsBundle.php`
- Modify: `config/routes.php`

- [ ] **Step 1: Register EventController in the bundle**

In `src/CookielessAnalyticsBundle.php`, add the import at the top:

```php
use Jackfumanchu\CookielessAnalyticsBundle\Controller\EventController;
```

In `loadExtension()`, add after the existing `$services->set(CollectController::class)` line:

```php
$services->set(EventController::class);
```

- [ ] **Step 2: Import EventController routes**

Replace the content of `config/routes.php` with:

```php
<?php

declare(strict_types=1);

use Jackfumanchu\CookielessAnalyticsBundle\Controller\CollectController;
use Jackfumanchu\CookielessAnalyticsBundle\Controller\EventController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import(CollectController::class, 'attribute')
        ->prefix('%cookieless_analytics.collect_prefix%');

    $routes->import(EventController::class, 'attribute')
        ->prefix('%cookieless_analytics.collect_prefix%');
};
```

- [ ] **Step 3: Run the full test suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass — unit tests for entity (2) + existing unit tests (19) + functional tests for EventController (8) + existing functional tests (8).

- [ ] **Step 4: Run PHPStan**

Run: `php -d memory_limit=512M vendor/bin/phpstan analyse --level 6`
Expected: No errors.

- [ ] **Step 5: Commit**

```bash
git add src/CookielessAnalyticsBundle.php config/routes.php
git commit -m "feat: register EventController in bundle and routes"
```

---

### Task 4: Inline JS Click Listener

**Files:**
- Modify: `src/Twig/CookielessAnalyticsExtension.php`
- Modify: `tests/Unit/Twig/CookielessAnalyticsExtensionTest.php`

- [ ] **Step 1: Update the Twig extension test to assert click listener**

Add these two new test methods to `tests/Unit/Twig/CookielessAnalyticsExtensionTest.php`:

```php
    #[Test]
    public function render_script_contains_click_listener(): void
    {
        $extension = new CookielessAnalyticsExtension('/ca');

        $script = $extension->renderScript();

        self::assertStringContainsString("closest('[data-ca-event]')", $script);
        self::assertStringContainsString('click', $script);
    }

    #[Test]
    public function render_script_contains_event_endpoint(): void
    {
        $extension = new CookielessAnalyticsExtension('/ca');

        $script = $extension->renderScript();

        self::assertStringContainsString('/ca/event', $script);
    }
```

- [ ] **Step 2: Run the new tests to verify they fail**

Run: `php vendor/bin/phpunit tests/Unit/Twig/CookielessAnalyticsExtensionTest.php`
Expected: 2 new tests FAIL (no click listener or `/event` endpoint in current script), 4 existing tests pass.

- [ ] **Step 3: Update the renderScript() method**

Replace the `renderScript()` method in `src/Twig/CookielessAnalyticsExtension.php`:

```php
    public function renderScript(): string
    {
        $base = rtrim($this->collectUrl, '/');
        $collectEndpoint = $base . '/collect';
        $eventEndpoint = $base . '/event';

        return <<<HTML
        <script>
        (function(){
            if(typeof navigator.sendBeacon!=='function')return;
            var b=function(u,d){navigator.sendBeacon(u,new Blob([d],{type:'application/json'}));};
            document.addEventListener('DOMContentLoaded',function(){
                b('{$collectEndpoint}',JSON.stringify({url:location.pathname+location.search,referrer:document.referrer||''}));
            });
            document.addEventListener('click',function(e){
                var el=e.target.closest('[data-ca-event]');
                if(!el)return;
                b('{$eventEndpoint}',JSON.stringify({name:el.getAttribute('data-ca-event'),value:el.getAttribute('data-ca-value')||null,pageUrl:location.pathname+location.search}));
            });
        })();
        </script>
        HTML;
    }
```

- [ ] **Step 4: Run all Twig extension tests**

Run: `php vendor/bin/phpunit tests/Unit/Twig/CookielessAnalyticsExtensionTest.php`
Expected: 6 tests pass (4 existing + 2 new).

- [ ] **Step 5: Update existing test assertion for Blob**

The existing `render_script_uses_blob_for_json_content_type` test asserts the old exact Blob string. The refactored script uses a `b()` helper function. Update the assertion in that test:

Replace line 54:
```php
        self::assertStringContainsString("new Blob([d],{type:'application/json'})", $script);
```
With:
```php
        self::assertStringContainsString("new Blob([d],{type:'application/json'})", $script);
```

Actually, the helper function still contains the same Blob string, so this assertion should still pass. Verify by running:

Run: `php vendor/bin/phpunit tests/Unit/Twig/CookielessAnalyticsExtensionTest.php`
Expected: 6 tests, 6 passed.

- [ ] **Step 6: Run PHPStan**

Run: `php -d memory_limit=512M vendor/bin/phpstan analyse src/Twig/CookielessAnalyticsExtension.php --level 6`
Expected: No errors.

- [ ] **Step 7: Commit**

```bash
git add src/Twig/CookielessAnalyticsExtension.php tests/Unit/Twig/CookielessAnalyticsExtensionTest.php
git commit -m "feat: add click listener for data-ca-event custom event tracking"
```

---

### Task 5: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass.

- [ ] **Step 2: Run PHPStan**

Run: `php -d memory_limit=512M vendor/bin/phpstan analyse --level 6`
Expected: No errors.

- [ ] **Step 3: Verify file structure matches spec**

Confirm these new files exist:
```
src/Entity/AnalyticsEvent.php
src/Controller/EventController.php
tests/Unit/Entity/AnalyticsEventTest.php
tests/Functional/Controller/EventControllerTest.php
```

Confirm these files were modified:
```
src/Twig/CookielessAnalyticsExtension.php
src/CookielessAnalyticsBundle.php
config/routes.php
tests/Unit/Twig/CookielessAnalyticsExtensionTest.php
```
