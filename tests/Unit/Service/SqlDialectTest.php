<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Jackfumanchu\CookielessAnalyticsBundle\Service\SqlDialect;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SqlDialectTest extends TestCase
{
    private function createDialect(string $platformClass): SqlDialect
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new $platformClass());

        return new SqlDialect($connection);
    }

    #[Test]
    public function date_to_day_postgresql(): void
    {
        $dialect = $this->createDialect(PostgreSQLPlatform::class);

        self::assertSame("TO_CHAR(viewed_at, 'YYYY-MM-DD')", $dialect->dateToDay('viewed_at'));
    }

    #[Test]
    public function date_to_day_mysql(): void
    {
        $dialect = $this->createDialect(MySQLPlatform::class);

        self::assertSame("DATE_FORMAT(viewed_at, '%Y-%m-%d')", $dialect->dateToDay('viewed_at'));
    }

    #[Test]
    public function date_to_day_sqlite(): void
    {
        $dialect = $this->createDialect(SQLitePlatform::class);

        self::assertSame("strftime('%Y-%m-%d', viewed_at)", $dialect->dateToDay('viewed_at'));
    }

    #[Test]
    public function extract_domain_postgresql(): void
    {
        $dialect = $this->createDialect(PostgreSQLPlatform::class);

        self::assertSame("SUBSTRING(referrer FROM '://([^/]+)')", $dialect->extractDomain('referrer'));
    }

    #[Test]
    public function extract_domain_mysql(): void
    {
        $dialect = $this->createDialect(MySQLPlatform::class);

        self::assertSame(
            "SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '://', -1), '/', 1)",
            $dialect->extractDomain('referrer'),
        );
    }

    #[Test]
    public function extract_domain_sqlite(): void
    {
        $dialect = $this->createDialect(SQLitePlatform::class);

        self::assertSame(
            "RTRIM(REPLACE(REPLACE(referrer, 'https://', ''), 'http://', ''), '/')",
            $dialect->extractDomain('referrer'),
        );
    }

    #[Test]
    public function throws_on_unsupported_platform(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(
            $this->createStub(\Doctrine\DBAL\Platforms\OraclePlatform::class)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported database platform');

        new SqlDialect($connection);
    }
}
