<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jackfumanchu\CookielessAnalyticsBundle\CookielessAnalyticsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new TwigBundle(),
            new CookielessAnalyticsBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/framework.yaml');
        $loader->load(__DIR__ . '/config/doctrine.yaml');
        $loader->load(__DIR__ . '/config/cookieless_analytics.yaml');

        if (PHP_VERSION_ID >= 80400) {
            $loader->load(static function ($container) {
                $container->loadFromExtension('doctrine', [
                    'orm' => ['enable_native_lazy_objects' => true],
                ]);
            });
        }
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return dirname(__DIR__, 2) . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return dirname(__DIR__, 2) . '/var/log';
    }
}
