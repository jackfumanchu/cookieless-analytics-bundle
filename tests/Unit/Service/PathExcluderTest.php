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
