<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\App;

use Composer\InstalledVersions;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jackfumanchu\CookielessAnalyticsBundle\CookielessAnalyticsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Jackfumanchu\CookielessAnalyticsBundle\Tests\App\Controller\TrackingTestController;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

        $loader->load(static function (ContainerBuilder $container) {
            $container->register(TrackingTestController::class)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('controller.service_arguments');

            $dbalConfig = [];
            $ormConfig = [];

            // TEST_TOKEN is set by ParaTest/Infection for parallel processes.
            // dbname_suffix with env(default::) is incompatible with Symfony 6.4's config validation,
            // so we only set it when the env var is actually present.
            if (isset($_SERVER['TEST_TOKEN'])) {
                $dbalConfig['dbname_suffix'] = '_' . $_SERVER['TEST_TOKEN'];
            }

            if (PHP_VERSION_ID >= 80400 && InstalledVersions::satisfies(new \Composer\Semver\VersionParser(), 'doctrine/doctrine-bundle', '>=3.1')) {
                $ormConfig['enable_native_lazy_objects'] = true;
            }

            if ($dbalConfig || $ormConfig) {
                $doctrine = [];
                if ($dbalConfig) {
                    $doctrine['dbal'] = $dbalConfig;
                }
                if ($ormConfig) {
                    $doctrine['orm'] = $ormConfig;
                }
                $container->loadFromExtension('doctrine', $doctrine);
            }
        });
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
