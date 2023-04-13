<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Services\EntityRemover;
use App\Tests\Services\FileStoreHandler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractBaseIntegrationTestCase extends WebTestCase
{
    protected EntityRemover $entityRemover;
    protected FileStoreHandler $localSourceStoreHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);
        $this->entityRemover = $entityRemover;

        $localSourceStoreHandler = self::getContainer()->get('app.tests.services.file_store_handler.local_source');
        \assert($localSourceStoreHandler instanceof FileStoreHandler);
        $this->localSourceStoreHandler = $localSourceStoreHandler;

        $this->clear();
    }

    protected function tearDown(): void
    {
        $this->clear();

        parent::tearDown();
    }

    private function clear(): void
    {
        $this->entityRemover->removeAll();
        $this->localSourceStoreHandler->clear();
    }
}
