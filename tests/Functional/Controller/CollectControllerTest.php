<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Functional\Controller;

use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CollectControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . PageView::class)->execute();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function collect_with_valid_payload_returns_204_and_persists(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/events?category=music',
            'referrer' => 'https://google.com',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageViews = $em->getRepository(PageView::class)->findAll();

        self::assertCount(1, $pageViews);
        self::assertSame('/events?category=music', $pageViews[0]->getPageUrl());
        self::assertSame('https://google.com', $pageViews[0]->getReferrer());
        self::assertSame(64, strlen($pageViews[0]->getFingerprint()));
    }

    #[Test]
    public function collect_without_referrer_stores_null(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/home',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageViews = $em->getRepository(PageView::class)->findAll();

        self::assertCount(1, $pageViews);
        self::assertNull($pageViews[0]->getReferrer());
    }

    #[Test]
    public function collect_with_empty_referrer_stores_null(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/home',
            'referrer' => '',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageViews = $em->getRepository(PageView::class)->findAll();

        self::assertNull($pageViews[0]->getReferrer());
    }

    #[Test]
    public function collect_with_missing_url_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'referrer' => 'https://google.com',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function collect_with_empty_url_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function collect_with_empty_body_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function collect_with_malformed_json_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{invalid json');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function collect_strips_sensitive_query_params(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/page?token=secret123&category=music',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageViews = $em->getRepository(PageView::class)->findAll();

        self::assertSame('/page?category=music', $pageViews[0]->getPageUrl());
    }

    #[Test]
    public function collect_with_excluded_path_returns_204_without_persisting(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url' => '/admin/dashboard',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageViews = $em->getRepository(PageView::class)->findAll();

        self::assertCount(0, $pageViews);
    }
}
