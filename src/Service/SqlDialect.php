<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

final class SqlDialect
{
    private string $platform;

    public function __construct(Connection $connection)
    {
        $dbPlatform = $connection->getDatabasePlatform();

        $this->platform = match (true) {
            $dbPlatform instanceof PostgreSQLPlatform => 'postgresql',
            $dbPlatform instanceof MySQLPlatform => 'mysql',
            $dbPlatform instanceof SQLitePlatform => 'sqlite',
            default => throw new \RuntimeException(sprintf(
                'Unsupported database platform: %s',
                $dbPlatform::class,
            )),
        };
    }

    public function dateToDay(string $column): string
    {
        return match ($this->platform) {
            'postgresql' => "TO_CHAR({$column}, 'YYYY-MM-DD')",
            'mysql' => "DATE_FORMAT({$column}, '%Y-%m-%d')",
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
        };
    }
}
