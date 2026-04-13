<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class PathExcluder
{
    /**
     * @param list<string> $patterns
     */
    public function __construct(
        private readonly array $patterns,
    ) {
    }

    public function isExcluded(string $url): bool
    {
        /** @infection-ignore-all — early return is an optimization; foreach on [] reaches the same return false */
        if ($this->patterns === []) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        foreach ($this->patterns as $pattern) {
            if (preg_match('#' . $pattern . '#', $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
