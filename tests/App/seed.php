<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$kernel = new \Jackfumanchu\CookielessAnalyticsBundle\Tests\App\Kernel('test', true);
$kernel->boot();

/** @var \Doctrine\ORM\EntityManagerInterface $em */
$em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

// Clean existing data
$em->createQuery('DELETE FROM ' . \Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView::class)->execute();
$em->createQuery('DELETE FROM ' . \Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent::class)->execute();

$fingerprints = array_map(fn (int $i) => hash('sha256', "visitor-{$i}"), range(1, 30));
$pages = ['/', '/docs/getting-started', '/pricing', '/blog/cookieless-future', '/docs/api-reference', '/about', '/changelog', '/blog/privacy-first-analytics'];
$referrers = ['https://google.com/search?q=analytics', 'https://twitter.com/post/123', 'https://news.ycombinator.com/item?id=1', 'https://reddit.com/r/php', 'https://dev.to/article', 'https://github.com/jackfumanchu', null, null];
$eventNames = ['cta_click', 'signup_start', 'signup_complete', 'docs_search', 'pricing_toggle', 'error_404', 'scroll_depth_75', 'download_sdk'];
$eventValues = ['hero-button', 'footer-button', 'sidebar-cta', 'pricing_monthly', 'pricing_annual', null];

$today = new DateTimeImmutable('today');

// Generate 30 days of page views
for ($day = 29; $day >= 0; $day--) {
    $date = $today->modify("-{$day} days");
    $dow = (int) $date->format('N');
    $baseViews = $dow <= 5 ? rand(80, 200) : rand(30, 80); // weekdays higher

    for ($v = 0; $v < $baseViews; $v++) {
        $fp = $fingerprints[array_rand($fingerprints)];
        $page = $pages[array_rand($pages)];
        $ref = $referrers[array_rand($referrers)];
        $hour = rand(6, 23);
        $minute = rand(0, 59);

        $em->persist(\Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView::create(
            fingerprint: $fp,
            pageUrl: $page,
            referrer: $ref,
            viewedAt: $date->setTime($hour, $minute),
        ));
    }

    // Events: ~30% of page view volume
    $eventCount = (int) ($baseViews * 0.3);
    for ($e = 0; $e < $eventCount; $e++) {
        $fp = $fingerprints[array_rand($fingerprints)];
        $name = $eventNames[array_rand($eventNames)];
        $value = $eventValues[array_rand($eventValues)];
        $page = $pages[array_rand($pages)];
        $hour = rand(6, 23);
        $minute = rand(0, 59);

        $em->persist(\Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent::create(
            fingerprint: $fp,
            name: $name,
            value: $value,
            pageUrl: $page,
            recordedAt: $date->setTime($hour, $minute),
        ));
    }

    // Flush per day to avoid memory buildup
    $em->flush();
    $em->clear();

    echo "Day {$date->format('Y-m-d')}: {$baseViews} views, {$eventCount} events\n";
}

$kernel->shutdown();
echo "\nDone. Seeded 30 days of data.\n";
