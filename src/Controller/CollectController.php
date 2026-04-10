<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use Jackfumanchu\CookielessAnalyticsBundle\Service\FingerprintGenerator;
use Jackfumanchu\CookielessAnalyticsBundle\Service\PathExcluder;
use Jackfumanchu\CookielessAnalyticsBundle\Service\UrlSanitizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CollectController
{
    public function __construct(
        private readonly FingerprintGenerator $fingerprintGenerator,
        private readonly UrlSanitizer $urlSanitizer,
        private readonly PathExcluder $pathExcluder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/collect', name: 'cookieless_analytics_collect', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body) || empty($body['url']) || !is_string($body['url'])) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $url = $this->urlSanitizer->sanitize($body['url']);

        if ($this->pathExcluder->isExcluded($url)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $referrer = null;
        if (!empty($body['referrer']) && is_string($body['referrer'])) {
            $referrer = $this->urlSanitizer->sanitize($body['referrer']);
        }

        $fingerprint = $this->fingerprintGenerator->generate(
            $request->getClientIp() ?? '0.0.0.0',
            $request->headers->get('User-Agent', ''),
            new \DateTimeImmutable(),
        );

        $pageView = PageView::create(
            fingerprint: $fingerprint,
            pageUrl: $url,
            referrer: $referrer,
            viewedAt: new \DateTimeImmutable(),
        );

        $this->entityManager->persist($pageView);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
