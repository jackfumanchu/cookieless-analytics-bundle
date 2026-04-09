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
