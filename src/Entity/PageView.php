<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ca_page_view')]
#[ORM\Index(columns: ['fingerprint'], name: 'idx_fingerprint')]
#[ORM\Index(columns: ['viewed_at'], name: 'idx_viewed_at')]
#[ORM\Index(columns: ['page_url'], name: 'idx_page_url', options: ['lengths' => [255]])]
class PageView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $fingerprint;

    #[ORM\Column(type: Types::STRING, length: 2048)]
    private string $pageUrl;

    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $referrer;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $viewedAt;

    private function __construct(
        string $fingerprint,
        string $pageUrl,
        ?string $referrer,
        \DateTimeImmutable $viewedAt,
    ) {
        $this->fingerprint = $fingerprint;
        $this->pageUrl = $pageUrl;
        $this->referrer = $referrer;
        $this->viewedAt = $viewedAt;
    }

    public static function create(
        string $fingerprint,
        string $pageUrl,
        ?string $referrer,
        \DateTimeImmutable $viewedAt,
    ): self {
        return new self($fingerprint, $pageUrl, $referrer, $viewedAt);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getPageUrl(): string
    {
        return $this->pageUrl;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function getViewedAt(): \DateTimeImmutable
    {
        return $this->viewedAt;
    }
}
