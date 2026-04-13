<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Twig;

use Composer\InstalledVersions;
use Jackfumanchu\CookielessAnalyticsBundle\Repository\PageViewRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class CookielessAnalyticsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly string $collectUrl,
        private readonly PageViewRepository $pageViewRepo,
    ) {
    }

    public function getGlobals(): array
    {
        /** @infection-ignore-all — getVersion() never returns null for an installed package */
        $version = InstalledVersions::getVersion('jackfumanchu/cookieless-analytics-bundle') ?? '0.0.0';
        $earliest = $this->pageViewRepo->findEarliestViewedAt();
        /** @infection-ignore-all — DateInterval::$days is already int */
        $daysActive = $earliest !== null ? (int) $earliest->diff(new \DateTimeImmutable('today'))->days + 1 : 0;

        return [
            'ca_bundle_version' => $version,
            'ca_days_active' => $daysActive,
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cookieless_analytics_script', [$this, 'renderScript'], ['is_safe' => ['html']]),
        ];
    }

    public function renderScript(): string
    {
        $base = rtrim($this->collectUrl, '/');
        $collectEndpoint = $base . '/collect';
        $eventEndpoint = $base . '/event';

        return <<<HTML
        <script>
        (function(){
            if(typeof navigator.sendBeacon!=='function')return;
            var b=function(u,d){navigator.sendBeacon(u,new Blob([d],{type:'application/json'}));};
            document.addEventListener('DOMContentLoaded',function(){
                b('{$collectEndpoint}',JSON.stringify({url:location.pathname+location.search,referrer:document.referrer||''}));
            });
            document.addEventListener('click',function(e){
                var el=e.target.closest('[data-ca-event]');
                if(!el)return;
                b('{$eventEndpoint}',JSON.stringify({name:el.getAttribute('data-ca-event'),value:el.getAttribute('data-ca-value')||null,pageUrl:location.pathname+location.search}));
            });
        })();
        </script>
        HTML;
    }
}
