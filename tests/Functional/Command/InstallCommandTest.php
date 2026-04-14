<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Functional\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InstallCommandTest extends KernelTestCase
{
    private Connection $connection;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $kernel = static::bootKernel();
        $application = new Application($kernel);

        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;

        $command = $application->find('cookieless:install');
        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function install_creates_tables_on_fresh_database(): void
    {
        $this->dropBundleTables();
        $this->tester->execute([]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('installed successfully', $this->tester->getDisplay());

        $this->connection->executeStatement("INSERT INTO ca_page_view (fingerprint, page_url, viewed_at) VALUES ('abc', '/test', '2026-01-01 00:00:00')");
        $this->connection->executeStatement("INSERT INTO ca_analytics_event (fingerprint, name, page_url, recorded_at) VALUES ('abc', 'test', '/test', '2026-01-01 00:00:00')");
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ca_page_view');
        self::assertSame(1, (int) $count);
    }

    #[Test]
    public function install_is_idempotent(): void
    {
        $this->tester->execute([]);
        self::assertSame(0, $this->tester->getStatusCode());

        $this->tester->execute([]);
        self::assertSame(0, $this->tester->getStatusCode());
    }

    #[Test]
    public function install_does_not_drop_unrelated_tables(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS unrelated_table');
        $this->connection->executeStatement('CREATE TABLE unrelated_table (id INTEGER PRIMARY KEY)');
        $this->connection->executeStatement("INSERT INTO unrelated_table (id) VALUES (1)");

        $this->tester->execute([]);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM unrelated_table');
        self::assertSame(1, (int) $count);

        $this->connection->executeStatement('DROP TABLE unrelated_table');
    }

    #[Test]
    public function page_view_table_has_expected_columns(): void
    {
        $this->tester->execute([]);

        $columns = $this->getColumnNames('ca_page_view');

        self::assertSame(['id', 'fingerprint', 'page_url', 'referrer', 'viewed_at'], $columns);
    }

    #[Test]
    public function analytics_event_table_has_expected_columns(): void
    {
        $this->tester->execute([]);

        $columns = $this->getColumnNames('ca_analytics_event');

        self::assertSame(['id', 'fingerprint', 'name', 'value', 'page_url', 'recorded_at'], $columns);
    }

    #[Test]
    public function page_view_table_has_expected_indexes(): void
    {
        $this->dropBundleTables();
        $this->tester->execute([]);

        $indexNames = $this->getIndexNames('ca_page_view');

        self::assertContains('idx_fingerprint', $indexNames);
        self::assertContains('idx_viewed_at', $indexNames);
        self::assertContains('idx_page_url', $indexNames);
    }

    #[Test]
    public function analytics_event_table_has_expected_indexes(): void
    {
        $this->dropBundleTables();
        $this->tester->execute([]);

        $indexNames = $this->getIndexNames('ca_analytics_event');

        self::assertContains('idx_event_fingerprint', $indexNames);
        self::assertContains('idx_event_recorded_at', $indexNames);
        self::assertContains('idx_event_name', $indexNames);
    }

    #[Test]
    public function install_creates_route_config_when_routes_dir_exists(): void
    {
        $routesDir = self::getContainer()->get('kernel')->getProjectDir() . '/config/routes';
        $routeFile = $routesDir . '/cookieless_analytics.yaml';

        @mkdir($routesDir, 0755, true);
        @unlink($routeFile);

        try {
            $this->tester->execute([]);

            self::assertSame(0, $this->tester->getStatusCode());
            self::assertFileExists($routeFile);
            $content = file_get_contents($routeFile);
            self::assertStringContainsString('RouteLoader', $content);
            self::assertStringEndsWith("\n", $content, 'Route config file should end with a newline');
            self::assertStringContainsString('Created config/routes/cookieless_analytics.yaml', $this->tester->getDisplay());
        } finally {
            @unlink($routeFile);
            @rmdir($routesDir);
        }
    }

    #[Test]
    public function install_skips_route_config_when_file_already_exists(): void
    {
        $routesDir = self::getContainer()->get('kernel')->getProjectDir() . '/config/routes';
        $routeFile = $routesDir . '/cookieless_analytics.yaml';

        @mkdir($routesDir, 0755, true);
        file_put_contents($routeFile, 'existing content');

        try {
            $this->tester->execute([]);

            self::assertSame(0, $this->tester->getStatusCode());
            self::assertSame('existing content', file_get_contents($routeFile), 'Existing route file should not be overwritten');
            self::assertStringNotContainsString('Created config/routes', $this->tester->getDisplay());
        } finally {
            @unlink($routeFile);
            @rmdir($routesDir);
        }
    }

    #[Test]
    public function install_skips_route_config_when_routes_dir_missing(): void
    {
        $routesDir = self::getContainer()->get('kernel')->getProjectDir() . '/config/routes';
        $routeFile = $routesDir . '/cookieless_analytics.yaml';

        // Ensure directory does not exist
        @unlink($routeFile);
        @rmdir($routesDir);

        $this->tester->execute([]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertFileDoesNotExist($routeFile);
        self::assertStringNotContainsString('Created config/routes', $this->tester->getDisplay());
    }

    private function dropBundleTables(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS ca_page_view');
        $this->connection->executeStatement('DROP TABLE IF EXISTS ca_analytics_event');
    }

    /**
     * @param non-empty-string $table
     * @return list<string>
     */
    private function getColumnNames(string $table): array
    {
        $sm = $this->connection->createSchemaManager();

        if (method_exists($sm, 'introspectTableColumnsByUnquotedName')) {
            return array_map(
                static fn ($column) => $column->getObjectName()->getIdentifier()->getValue(),
                $sm->introspectTableColumnsByUnquotedName($table),
            );
        }

        return array_values(array_map(
            static fn ($column) => $column->getName(),
            $sm->listTableColumns($table),
        ));
    }

    /**
     * @param non-empty-string $table
     * @return list<string>
     */
    private function getIndexNames(string $table): array
    {
        $sm = $this->connection->createSchemaManager();

        if (method_exists($sm, 'introspectTableIndexesByUnquotedName')) {
            return array_map(
                static fn ($index) => $index->getObjectName()->getIdentifier()->getValue(),
                $sm->introspectTableIndexesByUnquotedName($table),
            );
        }

        return array_values(array_map(
            static fn ($index) => $index->getName(),
            $sm->listTableIndexes($table),
        ));
    }
}
