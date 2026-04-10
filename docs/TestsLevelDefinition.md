# Test Levels Definition

## 1. Unit Tests

**Goal:**  
Verify the behavior of an isolated unit of code (class, method) — especially domain logic.

**Characteristics:**
- No real dependencies (stubs / mocks)
- No framework or container started
- Very fast
- Should be the most numerous
- Focused on business rules and invariants

**What to test at this level:**
- Entity domain logic (factories, invariants, state transitions)
- Service methods with stubbed/mocked dependencies
- Value objects, enums with behavior
- Pure computation (formatters, calculators)
- Twig components with pure logic (no rendering)

**Conventions:**
- Prefer **stubs** for dependencies that provide data (state verification)
- Use **mocks** only when you need to verify an interaction happened (e.g. `$em->persist()` was called)
- Use **Mother classes** for complex domain objects with many required fields. Simple objects with 1-2 fields don't need them — a `createStub()` is fine.
- For entities with many getters/setters but little logic, a single `testGettersSetters()` test covers CRAP score without pretending to test behavior.
- Test edge cases that mutation testing reveals (partial state, priority between fallbacks, trim on empty values).

**Directory:** `tests/Unit/`  
**Suite:** `php bin/phpunit --testsuite unit`

---

## 2. Integration Tests

**Goal:**  
Verify the interaction between components and real infrastructure (database, Doctrine, listeners).

**Characteristics:**
- Real dependencies used (database, Doctrine ORM)
- Symfony kernel booted (`KernelTestCase`)
- Database accessed via `dama/doctrine-test-bundle` (transaction isolation — each test rolls back automatically)
- Slower than unit tests but still fast
- Verify that queries, mappings, and listeners work correctly

**What to test at this level:**
- Repository custom queries (DQL, QueryBuilder, raw SQL)
- Doctrine entity listeners and event subscribers
- Complex query logic that can't be verified with mocks (JOINs, subqueries, aggregations)

**What NOT to test at this level:**
- Repositories with only a scaffolded `__construct` and no custom queries (e.g. `SpeakerRepository`, `VenueRepository`) — they only inherit from `ServiceEntityRepository` and have zero custom logic.

**Why this level matters:**  
Unit tests with mocked repositories cannot catch broken queries. A query may have correct logic in PHP but produce wrong SQL (e.g. a LEFT JOIN that duplicates rows across multiple related entities). Only a real database execution reveals this.

**Directory:** `tests/Integration/`  
**Suite:** `php bin/phpunit --testsuite integration`

**Database strategy:**  
`dama/doctrine-test-bundle` wraps each test in a transaction that is rolled back after the test. No manual cleanup needed. Configured in `phpunit.dist.xml`.

---

## 3. Functional Tests

**Goal:**  
Verify application behavior through HTTP entry points (request in, response out).

**Characteristics:**
- Full application started (`WebTestCase`)
- HTTP requests simulated via Symfony's test client
- Response validated (status code, redirects, content, flash messages)
- No real browser — server-side only
- Cover backend use cases end-to-end (controller + service + repository + database)

**What to test at this level:**
- Controller routes: correct status codes, redirects, rendered content
- Authentication and authorization (ROLE_ADMIN, CSRF tokens)
- Form submissions and validation
- Error cases (not found, forbidden, invalid input)
- Routing logic with branching (e.g. published vs archived vs draft → different templates or 404)

**Conventions:**
- Create the client first (`static::createClient()`), then get services from `self::getContainer()` — never boot the kernel before creating the client.
- Create test entities via `EntityManagerInterface` directly in each test — dama rolls back after each test.
- Test both happy path and error paths.

**Directory:** `tests/Functional/`  
**Suite:** `php bin/phpunit --testsuite functional`

---

## 4. External Service Tests

**Goal:**  
Test integration with third-party APIs that require real credentials and network access.

**Characteristics:**
- Real credentials required
- Network access needed
- Non-deterministic (depends on external state)
- Run separately, not in default CI pipeline
- Slow (network round-trips)

**What to test at this level:**
- Any third-party API integration

**Directory:** `tests/External/`  
**Run:** `php bin/phpunit -c phpunit_external.xml.dist`

**Status:** Not implemented yet. No external services integrated at this point.

---

## 5. End-to-End Tests (E2E)

**Goal:**  
Test the complete application in conditions close to real usage.

**Characteristics:**
- Real browser (Panther, Playwright)
- JavaScript executed
- User interactions simulated (click, type, navigate)
- Full stack involved (front + back + database)
- Slowest and most fragile

**What to test at this level:**
- Critical user journeys that depend on JavaScript (autocomplete, live components, modals)
- Flows that span multiple pages with client-side state
- Admin interactions requiring JS execution (EasyAdmin collection forms, drag/drop reordering, AJAX-loaded sub-forms)

**Examples (to implement):**
- Price management — add prices via EasyAdmin, reorder by drag/drop, verify positions are persisted correctly after form submission

**Directory:** `tests/E2E/`  
**Run:** `php bin/phpunit --testsuite e2e`

**Status:** Not implemented yet. To be considered for critical admin and user journeys.

---

## Summary

| Level         | Directory            | Config                       | Isolation  | Speed     | Tests |
|---------------|----------------------|------------------------------|------------|-----------|-------|
| Unit          | `tests/Unit/`        | `phpunit.dist.xml`           | High       | Very fast | 133   |
| Integration   | `tests/Integration/` | `phpunit.dist.xml`           | Medium     | Fast      | 6     |
| Functional    | `tests/Functional/`  | `phpunit.dist.xml`           | Low        | Medium    | 15    |
| External      | `tests/External/`    | `phpunit_external.xml.dist`  | Very low   | Slow      | —     |
| E2E           | `tests/E2E/`         | `phpunit.dist.xml`           | Very low   | Slowest   | —     |

**Running tests:**
- All suites: `php bin/phpunit`
- One suite: `php bin/phpunit --testsuite unit`
- Multiple suites: `php bin/phpunit --testsuite unit,integration`
- With coverage: `php bin/phpunit --coverage-html coverage/coverage-html`
- Mutation testing: `php vendor/bin/infection --threads=4`
- External only: `php bin/phpunit -c phpunit_external.xml.dist`

---

## Mutation Testing

Mutation testing (via [Infection](https://infection.github.io/)) verifies that tests actually detect code changes — not just execute code paths.

**Key metrics:**
- **MSI (Mutation Score Indicator):** percentage of mutants killed. Target: > 95%.
- **Covered Code MSI:** MSI restricted to covered code. Current: 99%.

**What to ignore in mutation reports:**
- Hardcoded placeholder data (e.g. `HomeController` fake arrays) — will be removed when replaced with real queries.
- `json_decode` depth parameter (512 ± 1) — impossible to trigger in practice.

**Run:** `php vendor/bin/infection --threads=4`  
**Log:** `coverage/infection.log`

---

## TDD Workflow

All new development must follow TDD (Test Driven Development):

1. **Red** — Write a failing test
2. **Green** — Write the minimal code to make it pass
3. **Refactor** — Clean up while keeping tests green

### Test doubles

- **Stubs** when possible (provide canned data, verify state)
- **Mocks** when you need to verify behavior (method was called, with specific arguments)
- **Mother classes** when building complex domain objects with many required fields — keeps tests readable and avoids duplication
