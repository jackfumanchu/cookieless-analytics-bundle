<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CookielessAnalyticsExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $collectUrl,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cookieless_analytics_script', [$this, 'renderScript'], ['is_safe' => ['html']]),
        ];
    }

    public function renderScript(): string
    {
        $endpoint = rtrim($this->collectUrl, '/') . '/collect';

        return <<<HTML
        <script>
        (function(){
            if(typeof navigator.sendBeacon!=='function')return;
            document.addEventListener('DOMContentLoaded',function(){
                var d=JSON.stringify({url:location.pathname+location.search,referrer:document.referrer||''});
                navigator.sendBeacon('{$endpoint}',new Blob([d],{type:'application/json'}));
            });
        })();
        </script>
        HTML;
    }
}
