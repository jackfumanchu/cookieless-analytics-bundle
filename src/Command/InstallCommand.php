<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Command;

use Doctrine\DBAL\Schema\Schema;
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

        $toSchema = $schemaTool->getSchemaFromMetadata($metadata);
        $fromSchema = $this->introspectBundleTables($toSchema);

        $comparator = $this->entityManager->getConnection()
            ->createSchemaManager()
            ->createComparator();

        $schemaDiff = $comparator->compareSchemas($fromSchema, $toSchema);
        $sql = $this->entityManager->getConnection()
            ->getDatabasePlatform()
            ->getAlterSchemaSQL($schemaDiff);

        if ($sql === []) {
            $io->success('CookielessAnalytics is already installed. Nothing to do.');

            return Command::SUCCESS;
        }

        foreach ($sql as $statement) {
            $this->entityManager->getConnection()->executeStatement($statement);
        }

        $io->success('CookielessAnalytics installed successfully.');

        return Command::SUCCESS;
    }

    private function introspectBundleTables(Schema $toSchema): Schema
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $tables = [];

        foreach ($toSchema->getTables() as $table) {
            if ($schemaManager->tableExists($table->getName())) {
                $tables[] = $schemaManager->introspectTable($table->getName());
            }
        }

        return new Schema($tables);
    }
}
