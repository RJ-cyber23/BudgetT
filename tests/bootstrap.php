<?php

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Rebuild the throwaway test database from the Doctrine mapping on every run,
// so tests never depend on a developer's local schema or data.
if ('test' === $_SERVER['APP_ENV'] && class_exists(SchemaTool::class)) {
    $kernel = new Kernel('test', true);
    $kernel->boot();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
    $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

    $schemaTool = new SchemaTool($entityManager);
    $schemaTool->dropSchema($metadata);
    $schemaTool->createSchema($metadata);

    $kernel->shutdown();
}
