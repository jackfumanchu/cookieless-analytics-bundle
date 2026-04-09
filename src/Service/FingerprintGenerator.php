<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class FingerprintGenerator
{
    public function generate(string $ip, string $userAgent, \DateTimeImmutable $date): string
    {
        return hash('sha256', $ip . $userAgent . $date->format('Y-m-d'));
    }
}
