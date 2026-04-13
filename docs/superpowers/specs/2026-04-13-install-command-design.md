# Install Command Design

## Problem

The bundle requires two database tables (`ca_page_view`, `ca_analytics_event`) but provides no setup mechanism. Users must manually run `doctrine:schema:update` which is not recommended for production, or figure out the schema themselves. Without the tables, the first page view produces an ugly SQL error.

## Goal

Provide a single console command that creates (or updates) the bundle's database tables. Users run it once after install. No migration files, no pollution of the host app's migration history, no copy-paste.

## Design

### Console command: `cookieless:install`

**Class:** `src/Command/InstallCommand.php`

**Registration:** Autowired via `CookielessAnalyticsBundle::loadExtension()`, same as other services.

**Behavior:**

The command uses Doctrine's `SchemaTool` scoped to the bundle's 2 entities (`PageView`, `AnalyticsEvent`).

1. Reads entity metadata for `PageView` and `AnalyticsEvent` only
2. Compares against the current database schema
3. If tables don't exist: creates them with all columns and indexes
4. If tables exist but schema differs (e.g. bundle upgrade added a column): applies the diff
5. If tables are already up to date: prints "nothing to do"

**Output examples:**

Fresh install:
```
$ php bin/console cookieless:install
Creating table ca_page_view... done.
Creating table ca_analytics_event... done.
CookielessAnalytics installed successfully.
```

Re-run (no changes):
```
$ php bin/console cookieless:install
CookielessAnalytics is already installed. Nothing to do.
```

Upgrade (schema changed):
```
$ php bin/console cookieless:install
Updating schema... done.
CookielessAnalytics schema updated successfully.
```

**Platform support:** `SchemaTool` generates platform-aware DDL via Doctrine DBAL. Works on PostgreSQL, MySQL, and SQLite without any dialect-specific code in the command.

### README update

Update the Installation section to document the command:

```bash
composer require jackfumanchu/cookieless-analytics-bundle
php bin/console cookieless:install
```

Remove the current migration instructions that reference a non-existent `migrations/` directory.

### Testing

**Functional test:** Boot the test kernel, run the command via `CommandTester`, assert exit code 0 and output contains success message. Run it twice to verify idempotency.

## Not in scope

- No migration files shipped with the bundle
- No auto-create tables on first HTTP request
- No try/catch in controllers for missing tables
- No `cookieless:uninstall` command
