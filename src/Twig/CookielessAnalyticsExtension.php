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
            if(typeof navigator.sendBeacon!=='function'||window.__ca)return;
            window.__ca=1;
            var b=function(u,d){navigator.sendBeacon(u,new Blob([d],{type:'application/json'}));};
            var ce='{$collectEndpoint}',last='';
            var t=function(){
                var url=location.pathname+location.search;
                if(url===last)return;
                var ref=last||document.referrer||'';
                last=url;
                b(ce,JSON.stringify({url:url,referrer:ref}));
            };
            var w=function(f){return function(){var r=f.apply(this,arguments);t();return r;};};
            history.pushState=w(history.pushState);
            history.replaceState=w(history.replaceState);
            window.addEventListener('popstate',t);
            document.addEventListener('DOMContentLoaded',t);
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
