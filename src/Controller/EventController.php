<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Service\FingerprintGenerator;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PathExcluder;
use Jackfumanchu\CookielessAnalyticsBundle\Service\UrlSanitizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController
{
    public function __construct(
        private readonly FingerprintGenerator $fingerprintGenerator,
        private readonly UrlSanitizer $urlSanitizer,
        private readonly PathExcluder $pathExcluder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/event', name: 'cookieless_analytics_event', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $body = json_decode($request->getContent(), true);

        /** @infection-ignore-all — subsequent empty($body['name']) guard also returns 400 for non-array input */
        if (!is_array($body)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        if (empty($body['name']) || !is_string($body['name'])) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        if (empty($body['pageUrl']) || !is_string($body['pageUrl'])) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $pageUrl = $this->urlSanitizer->sanitize($body['pageUrl']);

        if ($this->pathExcluder->isExcluded($pageUrl)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $value = null;
        if (!empty($body['value']) && is_string($body['value'])) {
            $value = $body['value'];
        }

        $fingerprint = $this->fingerprintGenerator->generate(
            /** @infection-ignore-all — test client always provides IP; coalesce only matters behind proxy */
            $request->getClientIp() ?? '0.0.0.0',
            $request->headers->get('User-Agent', ''),
            new \DateTimeImmutable(),
        );

        $event = AnalyticsEvent::create(
            fingerprint: $fingerprint,
            name: $body['name'],
            value: $value,
            pageUrl: $pageUrl,
            recordedAt: new \DateTimeImmutable(),
        );

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
