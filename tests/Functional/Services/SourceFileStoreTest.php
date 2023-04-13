<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Services\SourceFileStore;
use App\Tests\Services\FileStoreHandler;
use App\Tests\Services\SourceFileInspector;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SourceFileStoreTest extends WebTestCase
{
    private SourceFileStore $store;
    private FileStoreHandler $localSourceStoreHandler;
    private SourceFileInspector $sourceFileInspector;

    protected function setUp(): void
    {
        parent::setUp();

        $store = self::getContainer()->get(SourceFileStore::class);
        \assert($store instanceof SourceFileStore);
        $this->store = $store;

        $localSourceStoreHandler = self::getContainer()->get('app.tests.services.file_store_handler.local_source');
        \assert($localSourceStoreHandler instanceof FileStoreHandler);
        $this->localSourceStoreHandler = $localSourceStoreHandler;
        $this->localSourceStoreHandler->clear();

        $sourceFileInspector = self::getContainer()->get(SourceFileInspector::class);
        \assert($sourceFileInspector instanceof SourceFileInspector);
        $this->sourceFileInspector = $sourceFileInspector;
    }

    protected function tearDown(): void
    {
        $this->localSourceStoreHandler->clear();

        parent::tearDown();
    }

    public function testStoreContent(): void
    {
        $fixturePath = 'Test/chrome-open-index.yml';

        $content = (string) file_get_contents(__DIR__ . '/../../Fixtures/Basil/Test/chrome-open-index.yml');
        $path = $fixturePath;

        $this->store->storeContent($content, $path);

        self::assertTrue($this->sourceFileInspector->has($fixturePath));
    }
}
