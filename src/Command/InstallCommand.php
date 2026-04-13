<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cookieless:install',
    description: 'Create or update the CookielessAnalytics database tables',
)]
class InstallCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [
            $this->entityManager->getClassMetadata(PageView::class),
            $this->entityManager->getClassMetadata(AnalyticsEvent::class),
        ];

        $updateSql = $schemaTool->getUpdateSchemaSql($metadata, true);

        if ($updateSql === []) {
            $io->success('CookielessAnalytics is already installed. Nothing to do.');

            return Command::SUCCESS;
        }

        $schemaTool->updateSchema($metadata, true);

        $io->success('CookielessAnalytics installed successfully.');

        return Command::SUCCESS;
    }
}
