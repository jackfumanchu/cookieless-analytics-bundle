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

    /** @infection-ignore-all — constructor guarantees only postgresql/mysql/sqlite; default arm is for PHPStan */
    public function dateToDay(string $column): string
    {
        return match ($this->platform) {
            'postgresql' => "TO_CHAR({$column}, 'YYYY-MM-DD')",
            'mysql' => "DATE_FORMAT({$column}, '%Y-%m-%d')",
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
            default => throw new \LogicException("Unexpected platform: {$this->platform}"),
        };
    }

    /** @infection-ignore-all — constructor guarantees only postgresql/mysql/sqlite; default arm is for PHPStan */
    public function extractDomain(string $column): string
    {
        return match ($this->platform) {
            'postgresql' => "SUBSTRING({$column} FROM '://([^/]+)')",
            'mysql' => "SUBSTRING_INDEX(SUBSTRING_INDEX({$column}, '://', -1), '/', 1)",
            'sqlite' => "RTRIM(REPLACE(REPLACE({$column}, 'https://', ''), 'http://', ''), '/')",
            default => throw new \LogicException("Unexpected platform: {$this->platform}"),
        };
    }
}
