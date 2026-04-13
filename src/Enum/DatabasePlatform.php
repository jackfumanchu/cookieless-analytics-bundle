<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Enum;

enum DatabasePlatform: string
{
    case PostgreSQL = 'postgresql';
    case MySQL = 'mysql';
    case SQLite = 'sqlite';
}
