<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Functional\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . AnalyticsEvent::class)->execute();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function event_with_valid_payload_returns_204_and_persists(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'button_click',
            'value' => 'signup',
            'pageUrl' => '/home?category=music',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $events = $em->getRepository(AnalyticsEvent::class)->findAll();

        self::assertCount(1, $events);
        self::assertSame('button_click', $events[0]->getName());
        self::assertSame('signup', $events[0]->getValue());
        self::assertSame('/home?category=music', $events[0]->getPageUrl());
        self::assertSame(64, strlen($events[0]->getFingerprint()));
    }

    #[Test]
    public function event_without_value_stores_null(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'button_click',
            'pageUrl' => '/home',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $events = $em->getRepository(AnalyticsEvent::class)->findAll();

        self::assertCount(1, $events);
        self::assertNull($events[0]->getValue());
    }

    #[Test]
    public function event_with_empty_value_stores_null(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'button_click',
            'value' => '',
            'pageUrl' => '/home',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $events = $em->getRepository(AnalyticsEvent::class)->findAll();

        self::assertNull($events[0]->getValue());
    }

    #[Test]
    public function event_with_missing_name_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pageUrl' => '/home',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function event_with_missing_page_url_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'button_click',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function event_with_empty_body_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function event_with_malformed_json_returns_400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{invalid json');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function event_strips_sensitive_query_params_from_page_url(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'button_click',
            'pageUrl' => '/page?token=secret&category=music',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $events = $em->getRepository(AnalyticsEvent::class)->findAll();

        self::assertSame('/page?category=music', $events[0]->getPageUrl());
    }

    #[Test]
    public function event_with_excluded_page_url_returns_204_without_persisting(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ca/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'admin-click',
            'pageUrl' => '/admin/settings',
        ]));

        self::assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $events = $em->getRepository(AnalyticsEvent::class)->findAll();

        self::assertCount(0, $events);
    }
}
