# Core Data Collection Pipeline — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first vertical slice of the cookieless analytics bundle — from browser beacon to persisted page view.

**Architecture:** Thin controller delegates to two pure services (`FingerprintGenerator`, `UrlSanitizer`), persists a `PageView` entity via Doctrine, and is triggered by an inline JS script injected via a Twig function. Bundle configuration controls the route prefix and sensitive query param strip list.

**Tech Stack:** PHP 8.2+, Symfony 7.4+, Doctrine ORM 3.x, Twig 3/4, PHPUnit 13, PHPStan level 6

**Spec:** `docs/superpowers/specs/2026-04-10-core-data-collection-design.md`

---

## File Structure

| File | Responsibility |
|---|---|
| `src/Entity/PageView.php` | Doctrine entity — stores a single page view record |
| `src/Service/FingerprintGenerator.php` | Pure service — generates daily SHA-256 fingerprint from IP + UA + date |
| `src/Service/UrlSanitizer.php` | Pure service — strips sensitive query params from URLs |
| `src/Controller/CollectController.php` | Thin controller — validates beacon payload, delegates to services, persists |
| `src/DependencyInjection/Configuration.php` | Bundle config tree definition |
| `src/DependencyInjection/CookielessAnalyticsExtension.php` | Loads services, injects config values |
| `src/Twig/CookielessAnalyticsExtension.php` | Registers `cookieless_analytics_script()` Twig function |
| `config/routes.php` | Bundle route import file |
| `config/services.php` | Bundle service definitions |
| `tests/Unit/Service/FingerprintGeneratorTest.php` | Unit tests for fingerprint generation |
| `tests/Unit/Service/UrlSanitizerTest.php` | Unit tests for URL sanitization |
| `tests/Unit/Entity/PageViewTest.php` | Unit tests for entity factory method |
| `tests/Functional/Controller/CollectControllerTest.php` | Functional tests for the collect endpoint |

---

### Task 1: PageView Entity

**Files:**
- Create: `tests/Unit/Entity/PageViewTest.php`
- Create: `src/Entity/PageView.php`

- [ ] **Step 1: Write the failing test for the factory method**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Entity;

use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PageViewTest extends TestCase
{
    #[Test]
    public function create_returns_page_view_with_all_fields(): void
    {
        $viewedAt = new \DateTimeImmutable('2026-04-10 14:30:00');

        $pageView = PageView::create(
            fingerprint: 'abc123def456abc123def456abc123def456abc123def456abc123def456abcd',
            pageUrl: '/events?category=music',
            referrer: 'https://google.com/search?q=events',
            viewedAt: $viewedAt,
        );

        self::assertSame('abc123def456abc123def456abc123def456abc123def456abc123def456abcd', $pageView->getFingerprint());
        self::assertSame('/events?category=music', $pageView->getPageUrl());
        self::assertSame('https://google.com/search?q=events', $pageView->getReferrer());
        self::assertSame($viewedAt, $pageView->getViewedAt());
        self::assertNull($pageView->getId());
    }

    #[Test]
    public function create_accepts_null_referrer(): void
    {
        $pageView = PageView::create(
            fingerprint: 'abc123def456abc123def456abc123def456abc123def456abc123def456abcd',
            pageUrl: '/home',
            referrer: null,
            viewedAt: new \DateTimeImmutable(),
        );

        self::assertNull($pageView->getReferrer());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Entity/PageViewTest.php -v`
Expected: FAIL — class `PageView` does not exist.

- [ ] **Step 3: Write the PageView entity**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ca_page_view')]
#[ORM\Index(columns: ['fingerprint'], name: 'idx_fingerprint')]
#[ORM\Index(columns: ['viewed_at'], name: 'idx_viewed_at')]
#[ORM\Index(columns: ['page_url'], name: 'idx_page_url', options: ['lengths' => [255]])]
class PageView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $fingerprint;

    #[ORM\Column(type: Types::STRING, length: 2048)]
    private string $pageUrl;

    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $referrer;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $viewedAt;

    private function __construct(
        string $fingerprint,
        string $pageUrl,
        ?string $referrer,
        \DateTimeImmutable $viewedAt,
    ) {
        $this->fingerprint = $fingerprint;
        $this->pageUrl = $pageUrl;
        $this->referrer = $referrer;
        $this->viewedAt = $viewedAt;
    }

    public static function create(
        string $fingerprint,
        string $pageUrl,
        ?string $referrer,
        \DateTimeImmutable $viewedAt,
    ): self {
        return new self($fingerprint, $pageUrl, $referrer, $viewedAt);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getPageUrl(): string
    {
        return $this->pageUrl;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function getViewedAt(): \DateTimeImmutable
    {
        return $this->viewedAt;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Entity/PageViewTest.php -v`
Expected: 2 tests, 2 passed.

- [ ] **Step 5: Run PHPStan**

Run: `php vendor/bin/phpstan analyse src/Entity/PageView.php tests/Unit/Entity/PageViewTest.php --level 6`
Expected: No errors.

- [ ] **Step 6: Commit**

```bash
git add src/Entity/PageView.php tests/Unit/Entity/PageViewTest.php
git commit -m "feat: add PageView entity with factory method and unit tests"
```

---

### Task 2: FingerprintGenerator Service

**Files:**
- Create: `tests/Unit/Service/FingerprintGeneratorTest.php`
- Create: `src/Service/FingerprintGenerator.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\FingerprintGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FingerprintGeneratorTest extends TestCase
{
    private FingerprintGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new FingerprintGenerator();
    }

    #[Test]
    public function generate_returns_64_char_hex_string(): void
    {
        $result = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', new \DateTimeImmutable('2026-04-10'));

        self::assertSame(64, strlen($result));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    #[Test]
    public function generate_is_deterministic(): void
    {
        $date = new \DateTimeImmutable('2026-04-10');

        $result1 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', $date);
        $result2 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', $date);

        self::assertSame($result1, $result2);
    }

    #[Test]
    public function generate_returns_different_hash_for_different_date(): void
    {
        $result1 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', new \DateTimeImmutable('2026-04-10'));
        $result2 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', new \DateTimeImmutable('2026-04-11'));

        self::assertNotSame($result1, $result2);
    }

    #[Test]
    public function generate_returns_different_hash_for_different_ip(): void
    {
        $date = new \DateTimeImmutable('2026-04-10');

        $result1 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', $date);
        $result2 = $this->generator->generate('192.168.1.1', 'Mozilla/5.0', $date);

        self::assertNotSame($result1, $result2);
    }

    #[Test]
    public function generate_returns_different_hash_for_different_user_agent(): void
    {
        $date = new \DateTimeImmutable('2026-04-10');

        $result1 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', $date);
        $result2 = $this->generator->generate('127.0.0.1', 'Chrome/120', $date);

        self::assertNotSame($result1, $result2);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php vendor/bin/phpunit tests/Unit/Service/FingerprintGeneratorTest.php -v`
Expected: FAIL — class `FingerprintGenerator` does not exist.

- [ ] **Step 3: Write the FingerprintGenerator service**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class FingerprintGenerator
{
    public function generate(string $ip, string $userAgent, \DateTimeImmutable $date): string
    {
        return hash('sha256', $ip . $userAgent . $date->format('Y-m-d'));
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php vendor/bin/phpunit tests/Unit/Service/FingerprintGeneratorTest.php -v`
Expected: 5 tests, 5 passed.

- [ ] **Step 5: Run PHPStan**

Run: `php vendor/bin/phpstan analyse src/Service/FingerprintGenerator.php tests/Unit/Service/FingerprintGeneratorTest.php --level 6`
Expected: No errors.

- [ ] **Step 6: Commit**

```bash
git add src/Service/FingerprintGenerator.php tests/Unit/Service/FingerprintGeneratorTest.php
git commit -m "feat: add FingerprintGenerator service with unit tests"
```

---

### Task 3: UrlSanitizer Service

**Files:**
- Create: `tests/Unit/Service/UrlSanitizerTest.php`
- Create: `src/Service/UrlSanitizer.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\UrlSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UrlSanitizerTest extends TestCase
{
    private UrlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new UrlSanitizer(['token', 'password', 'secret']);
    }

    #[Test]
    public function sanitize_strips_configured_params(): void
    {
        $result = $this->sanitizer->sanitize('/page?token=abc&category=music');

        self::assertSame('/page?category=music', $result);
    }

    #[Test]
    public function sanitize_strips_multiple_configured_params(): void
    {
        $result = $this->sanitizer->sanitize('/page?token=abc&password=xyz&category=music');

        self::assertSame('/page?category=music', $result);
    }

    #[Test]
    public function sanitize_returns_path_only_when_all_params_stripped(): void
    {
        $result = $this->sanitizer->sanitize('/page?token=abc&password=xyz');

        self::assertSame('/page', $result);
    }

    #[Test]
    public function sanitize_preserves_url_without_query_string(): void
    {
        $result = $this->sanitizer->sanitize('/page');

        self::assertSame('/page', $result);
    }

    #[Test]
    public function sanitize_preserves_safe_params(): void
    {
        $result = $this->sanitizer->sanitize('/search?q=shoes&page=2');

        self::assertSame('/search?q=shoes&page=2', $result);
    }

    #[Test]
    public function sanitize_handles_encoded_params(): void
    {
        $result = $this->sanitizer->sanitize('/page?token=abc%20def&category=music');

        self::assertSame('/page?category=music', $result);
    }

    #[Test]
    public function sanitize_handles_empty_strip_list(): void
    {
        $sanitizer = new UrlSanitizer([]);

        $result = $sanitizer->sanitize('/page?token=abc&category=music');

        self::assertSame('/page?token=abc&category=music', $result);
    }

    #[Test]
    public function sanitize_handles_full_url_with_host(): void
    {
        $result = $this->sanitizer->sanitize('https://example.com/page?token=abc&category=music');

        self::assertSame('https://example.com/page?category=music', $result);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php vendor/bin/phpunit tests/Unit/Service/UrlSanitizerTest.php -v`
Expected: FAIL — class `UrlSanitizer` does not exist.

- [ ] **Step 3: Write the UrlSanitizer service**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class UrlSanitizer
{
    /**
     * @param list<string> $stripParams
     */
    public function __construct(
        private readonly array $stripParams,
    ) {
    }

    public function sanitize(string $url): string
    {
        $parsed = parse_url($url);

        if (!isset($parsed['query']) || $this->stripParams === []) {
            return $url;
        }

        parse_str($parsed['query'], $queryParams);

        $filtered = array_diff_key($queryParams, array_flip($this->stripParams));

        $base = '';
        if (isset($parsed['scheme'], $parsed['host'])) {
            $base = $parsed['scheme'] . '://' . $parsed['host'];
        }

        $path = $parsed['path'] ?? '/';

        if ($filtered === []) {
            return $base . $path;
        }

        return $base . $path . '?' . http_build_query($filtered);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php vendor/bin/phpunit tests/Unit/Service/UrlSanitizerTest.php -v`
Expected: 8 tests, 8 passed.

- [ ] **Step 5: Run PHPStan**

Run: `php vendor/bin/phpstan analyse src/Service/UrlSanitizer.php tests/Unit/Service/UrlSanitizerTest.php --level 6`
Expected: No errors.

- [ ] **Step 6: Commit**

```bash
git add src/Service/UrlSanitizer.php tests/Unit/Service/UrlSanitizerTest.php
git commit -m "feat: add UrlSanitizer service with unit tests"
```

---

### Task 4: Bundle Configuration

**Files:**
- Create: `src/DependencyInjection/Configuration.php`
- Modify: `src/CookielessAnalyticsBundle.php`
- Create: `config/services.php`
- Create: `config/routes.php`

- [ ] **Step 1: Create the Configuration class**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('cookieless_analytics');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('collect_prefix')
                    ->defaultValue('/ca')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('strip_query_params')
                    ->scalarPrototype()->end()
                    ->defaultValue(['token', 'password', 'key', 'secret', 'email'])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
```

- [ ] **Step 2: Create the Extension class**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class CookielessAnalyticsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('cookieless_analytics.collect_prefix', $config['collect_prefix']);
        $container->setParameter('cookieless_analytics.strip_query_params', $config['strip_query_params']);

        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/config'));
        $loader->load('services.php');
    }
}
```

- [ ] **Step 3: Create the services config**

```php
<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Jackfumanchu\CookielessAnalyticsBundle\Controller\CollectController;
use Jackfumanchu\CookielessAnalyticsBundle\Service\FingerprintGenerator;
use Jackfumanchu\CookielessAnalyticsBundle\Service\UrlSanitizer;
use Jackfumanchu\CookielessAnalyticsBundle\Twig\CookielessAnalyticsExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(FingerprintGenerator::class);

    $services->set(UrlSanitizer::class)
        ->arg('$stripParams', param('cookieless_analytics.strip_query_params'));

    $services->set(CollectController::class)
        ->arg('$fingerprintGenerator', service(FingerprintGenerator::class))
        ->arg('$urlSanitizer', service(UrlSanitizer::class))
        ->arg('$entityManager', service('doctrine.orm.entity_manager'))
        ->tag('controller.service_arguments');

    $services->set(CookielessAnalyticsExtension::class)
        ->arg('$collectUrl', param('cookieless_analytics.collect_prefix'))
        ->tag('twig.extension');
};
```

- [ ] **Step 4: Create the routes config**

```php
<?php

declare(strict_types=1);

use Jackfumanchu\CookielessAnalyticsBundle\Controller\CollectController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import(CollectController::class, 'attribute')
        ->prefix('%cookieless_analytics.collect_prefix%');
};
```

- [ ] **Step 5: Run PHPStan on the new files**

Run: `php vendor/bin/phpstan analyse src/DependencyInjection/ config/ --level 6`
Expected: No errors (controller and Twig extension don't exist yet — PHPStan may flag those class references, which is expected and will be resolved in subsequent tasks).

- [ ] **Step 6: Commit**

```bash
git add src/DependencyInjection/Configuration.php src/DependencyInjection/CookielessAnalyticsExtension.php config/services.php config/routes.php
git commit -m "feat: add bundle configuration, service definitions, and route import"
```

---

### Task 5: CollectController

**Files:**
- Create: `tests/Functional/Controller/CollectControllerTest.php`
- Create: `src/Controller/CollectController.php`

- [ ] **Step 1: Write the failing functional tests**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Functional\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CollectControllerTest extends WebTestCase
{
    #[Test]
    public function collect_with_valid_payload_returns_204_and_persists(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/events?category=music',
            'referrer' => 'https://google.com',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageViews = $em->getRepository(PageView::class)->findAll();

        self::assertCount(1, $pageViews);
        self::assertSame('/events?category=music', $pageViews[0]->getPageUrl());
        self::assertSame('https://google.com', $pageViews[0]->getReferrer());
        self::assertSame(64, strlen($pageViews[0]->getFingerprint()));
    }

    #[Test]
    public function collect_without_referrer_stores_null(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/home',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageViews = $em->getRepository(PageView::class)->findAll();

        self::assertCount(1, $pageViews);
        self::assertNull($pageViews[0]->getReferrer());
    }

    #[Test]
    public function collect_with_empty_referrer_stores_null(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/home',
            'referrer' => '',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageViews = $em->getRepository(PageView::class)->findAll();

        self::assertNull($pageViews[0]->getReferrer());
    }

    #[Test]
    public function collect_with_missing_url_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'referrer' => 'https://google.com',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function collect_with_empty_url_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function collect_with_empty_body_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function collect_with_malformed_json_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{invalid json');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function collect_strips_sensitive_query_params(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/page?token=secret123&category=music',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageViews = $em->getRepository(PageView::class)->findAll();

        self::assertSame('/page?category=music', $pageViews[0]->getPageUrl());
    }
}
```

> **Note:** These functional tests require a working Symfony test kernel and database. Before running them, you will need a minimal test application setup (kernel, database config). If not already present, create the necessary test infrastructure first. This is addressed in Task 7.

- [ ] **Step 2: Write the CollectController**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use Jackfumanchu\CookielessAnalyticsBundle\Service\FingerprintGenerator;
use Jackfumanchu\CookielessAnalyticsBundle\Service\UrlSanitizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CollectController
{
    public function __construct(
        private readonly FingerprintGenerator $fingerprintGenerator,
        private readonly UrlSanitizer $urlSanitizer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/collect', name: 'cookieless_analytics_collect', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body) || empty($body['url']) || !is_string($body['url'])) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $url = $this->urlSanitizer->sanitize($body['url']);

        $referrer = null;
        if (!empty($body['referrer']) && is_string($body['referrer'])) {
            $referrer = $this->urlSanitizer->sanitize($body['referrer']);
        }

        $fingerprint = $this->fingerprintGenerator->generate(
            $request->getClientIp() ?? '0.0.0.0',
            $request->headers->get('User-Agent', ''),
            new \DateTimeImmutable(),
        );

        $pageView = PageView::create(
            fingerprint: $fingerprint,
            pageUrl: $url,
            referrer: $referrer,
            viewedAt: new \DateTimeImmutable(),
        );

        $this->entityManager->persist($pageView);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 3: Run PHPStan on the controller**

Run: `php vendor/bin/phpstan analyse src/Controller/CollectController.php --level 6`
Expected: No errors.

- [ ] **Step 4: Commit**

```bash
git add src/Controller/CollectController.php tests/Functional/Controller/CollectControllerTest.php
git commit -m "feat: add CollectController with functional tests"
```

---

### Task 6: Twig Extension

**Files:**
- Create: `src/Twig/CookielessAnalyticsExtension.php`

- [ ] **Step 1: Write the Twig extension**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CookielessAnalyticsExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $collectUrl,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cookieless_analytics_script', [$this, 'renderScript'], ['is_safe' => ['html']]),
        ];
    }

    public function renderScript(): string
    {
        $endpoint = rtrim($this->collectUrl, '/') . '/collect';

        return <<<HTML
        <script>
        (function(){
            if(typeof navigator.sendBeacon!=='function')return;
            document.addEventListener('DOMContentLoaded',function(){
                navigator.sendBeacon('{$endpoint}',JSON.stringify({
                    url:location.pathname+location.search,
                    referrer:document.referrer||''
                }));
            });
        })();
        </script>
        HTML;
    }
}
```

> **Note:** The `sendBeacon` call does not set the `Content-Type` header to `application/json` by default when sending a string. We need to wrap the payload in a `Blob` with the correct MIME type so the controller receives proper JSON. Updated version:

```php
    public function renderScript(): string
    {
        $endpoint = rtrim($this->collectUrl, '/') . '/collect';

        return <<<HTML
        <script>
        (function(){
            if(typeof navigator.sendBeacon!=='function')return;
            document.addEventListener('DOMContentLoaded',function(){
                var d=JSON.stringify({url:location.pathname+location.search,referrer:document.referrer||''});
                navigator.sendBeacon('{$endpoint}',new Blob([d],{type:'application/json'}));
            });
        })();
        </script>
        HTML;
    }
```

- [ ] **Step 2: Run PHPStan**

Run: `php vendor/bin/phpstan analyse src/Twig/CookielessAnalyticsExtension.php --level 6`
Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add src/Twig/CookielessAnalyticsExtension.php
git commit -m "feat: add Twig extension with cookieless_analytics_script() function"
```

---

### Task 7: Test Infrastructure

The functional tests (Task 5) require a minimal Symfony test kernel, database config, and bootstrap. This task sets up the test application that the bundle's functional tests boot.

**Files:**
- Create: `tests/App/Kernel.php`
- Create: `tests/App/config/framework.yaml`
- Create: `tests/App/config/doctrine.yaml`
- Create: `tests/App/config/routes.php`
- Modify: `tests/bootstrap.php`
- Modify: `phpunit.dist.xml`

- [ ] **Step 1: Create the test kernel**

```php
<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jackfumanchu\CookielessAnalyticsBundle\CookielessAnalyticsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class Kernel extends BaseKernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new TwigBundle(),
            new CookielessAnalyticsBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/framework.yaml');
        $loader->load(__DIR__ . '/config/doctrine.yaml');
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return dirname(__DIR__, 2) . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return dirname(__DIR__, 2) . '/var/log';
    }
}
```

- [ ] **Step 2: Create framework config**

```yaml
# tests/App/config/framework.yaml
framework:
    test: true
    secret: 'test-secret'
    http_method_override: false
    handle_all_throwables: true
    router:
        resource: '%kernel.project_dir%/config/routes.php'
        utf8: true
```

- [ ] **Step 3: Create doctrine config**

```yaml
# tests/App/config/doctrine.yaml
doctrine:
    dbal:
        driver: pdo_sqlite
        url: 'sqlite:///%kernel.project_dir%/../../var/test.db'
    orm:
        auto_generate_proxy_classes: true
        auto_mapping: true
        mappings:
            CookielessAnalyticsBundle:
                is_bundle: false
                type: attribute
                dir: '%kernel.project_dir%/../../src/Entity'
                prefix: 'Jackfumanchu\CookielessAnalyticsBundle\Entity'
                alias: CookielessAnalytics
```

- [ ] **Step 4: Create test routes config**

```php
<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import(__DIR__ . '/../../../config/routes.php');
};
```

- [ ] **Step 5: Update bootstrap.php**

Replace the content of `tests/bootstrap.php`:

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
```

- [ ] **Step 6: Update phpunit.dist.xml to set the kernel class**

Add the following `<server>` entry inside the `<php>` block of `phpunit.dist.xml`:

```xml
<server name="KERNEL_CLASS" value="Jackfumanchu\CookielessAnalyticsBundle\Tests\App\Kernel" force="true" />
```

Also update testsuites to separate unit and functional:

```xml
<testsuites>
    <testsuite name="unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="functional">
        <directory>tests/Functional</directory>
    </testsuite>
</testsuites>
```

- [ ] **Step 7: Add test dependencies to composer.json**

Add to `require-dev`:

```json
"doctrine/doctrine-bundle": "^2.13",
"symfony/twig-bundle": "^7.4 || ^8.0"
```

Then run: `composer update`

- [ ] **Step 8: Create the test database schema**

Run: `php vendor/bin/phpunit tests/Unit/ -v` (unit tests should pass without DB)
Then create the SQLite schema for functional tests:

```bash
php tests/App/bin/console doctrine:schema:create --env=test
```

Or alternatively, add schema creation to the bootstrap if needed.

- [ ] **Step 9: Run the full test suite**

Run: `php vendor/bin/phpunit -v`
Expected: All unit tests (15) and functional tests (8) pass.

- [ ] **Step 10: Run PHPStan on everything**

Run: `php vendor/bin/phpstan analyse --level 6`
Expected: No errors.

- [ ] **Step 11: Commit**

```bash
git add tests/App/ tests/bootstrap.php phpunit.dist.xml composer.json composer.lock
git commit -m "feat: add test infrastructure for functional tests"
```

---

### Task 8: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `php vendor/bin/phpunit -v`
Expected: All tests pass (unit + functional).

- [ ] **Step 2: Run PHPStan**

Run: `php vendor/bin/phpstan analyse --level 6`
Expected: No errors.

- [ ] **Step 3: Verify file structure matches spec**

Confirm the following files exist:

```
src/Controller/CollectController.php
src/DependencyInjection/CookielessAnalyticsExtension.php
src/DependencyInjection/Configuration.php
src/Entity/PageView.php
src/Service/FingerprintGenerator.php
src/Service/UrlSanitizer.php
src/Twig/CookielessAnalyticsExtension.php
src/CookielessAnalyticsBundle.php
config/routes.php
config/services.php
tests/Unit/Entity/PageViewTest.php
tests/Unit/Service/FingerprintGeneratorTest.php
tests/Unit/Service/UrlSanitizerTest.php
tests/Functional/Controller/CollectControllerTest.php
```

- [ ] **Step 4: Final commit if any cleanup was needed**

```bash
git add -A
git commit -m "chore: final cleanup for core data collection pipeline"
```
