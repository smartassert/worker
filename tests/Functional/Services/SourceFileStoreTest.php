<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Model\UploadedSource;
use App\Services\SourceFileStore;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\FileStoreHandler;
use App\Tests\Services\UploadedFileFactory;
use Symfony\Component\HttpFoundation\File\File;

class SourceFileStoreTest extends AbstractBaseFunctionalTest
{
    private SourceFileStore $store;
    private FileStoreHandler $localSourceStoreHandler;
    private FileStoreHandler $uploadStoreHandler;
    private UploadedFileFactory $uploadedFileFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $store = self::getContainer()->get(SourceFileStore::class);
        \assert($store instanceof SourceFileStore);
        $this->store = $store;

        $uploadedFileFactory = self::getContainer()->get(UploadedFileFactory::class);
        \assert($uploadedFileFactory instanceof UploadedFileFactory);
        $this->uploadedFileFactory = $uploadedFileFactory;

        $localSourceStoreHandler = self::getContainer()->get('app.tests.services.file_store_handler.local_source');
        \assert($localSourceStoreHandler instanceof FileStoreHandler);
        $this->localSourceStoreHandler = $localSourceStoreHandler;
        $this->localSourceStoreHandler->clear();

        $uploadStoreHandler = self::getContainer()->get('app.tests.services.file_store_handler.uploaded');
        \assert($uploadStoreHandler instanceof FileStoreHandler);
        $this->uploadStoreHandler = $uploadStoreHandler;
        $this->uploadStoreHandler->clear();
    }

    protected function tearDown(): void
    {
        $this->localSourceStoreHandler->clear();
        $this->uploadStoreHandler->clear();

        parent::tearDown();
    }

    /**
     * @dataProvider storeDataProvider
     */
    public function testStore(
        string $fixturePath,
        File $expectedFile
    ): void {
        self::assertFalse($this->store->has($fixturePath));

        $expectedFilePath = $expectedFile->getPathname();
        self::assertFileDoesNotExist($expectedFilePath);

        $uploadedSource = $this->createUploadedSource($fixturePath);
        $file = $this->store->store($uploadedSource, $fixturePath);

        self::assertEquals($expectedFile->getPathname(), $file->getPathname());
        self::assertFileExists($expectedFilePath);
        self::assertTrue($this->store->has($fixturePath));
    }

    /**
     * @return array<mixed>
     */
    public function storeDataProvider(): array
    {
        return [
            'default' => [
                'fixturePath' => 'Test/chrome-open-index.yml',
                'expectedFile' => new File(
                    getcwd() . '/tests/Fixtures/CompilerSource/Test/chrome-open-index.yml',
                    false
                ),
            ],
        ];
    }

    private function createUploadedSource(string $relativePath): UploadedSource
    {
        $uploadedFilePath = $this->uploadStoreHandler->copyFixture($relativePath);
        $uploadedFile = $this->uploadedFileFactory->create($uploadedFilePath);

        return new UploadedSource($relativePath, $uploadedFile);
    }
}
