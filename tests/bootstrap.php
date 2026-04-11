<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Create the database and schema for functional tests.
// When TEST_TOKEN is set (ParaTest/Infection parallel runs),
// each process gets its own database via dbname_suffix.
(static function (): void {
    $kernel = new \Jackfumanchu\CookielessAnalyticsBundle\Tests\App\Kernel('test', true);
    $kernel->boot();

    /** @var \Doctrine\ORM\EntityManagerInterface $em */
    $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
    $conn = $em->getConnection();

    $params = $conn->getParams();
    $dbName = $params['dbname'];

    // Connect without a database to create it if needed
    $tmpParams = $params;
    $tmpParams['dbname'] = 'postgres';
    $tmpConn = \Doctrine\DBAL\DriverManager::getConnection($tmpParams);

    try {
        $tmpConn->executeStatement(sprintf('CREATE DATABASE "%s"', $dbName));
    } catch (\Doctrine\DBAL\Exception) {
        // Database already exists — ignore
    }
    $tmpConn->close();

    // Now create the schema
    $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
    $metadata = $em->getMetadataFactory()->getAllMetadata();

    $schemaTool->dropSchema($metadata);
    $schemaTool->createSchema($metadata);

    $kernel->shutdown();
})();
