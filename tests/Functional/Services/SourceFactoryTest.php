<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Source;
use App\Exception\MissingTestSourceException;
use App\Model\Manifest;
use App\Model\UploadedFileKey;
use App\Model\UploadedSource;
use App\Model\UploadedSourceCollection;
use App\Model\YamlSourceCollection;
use App\Services\ManifestFactory;
use App\Services\SourceFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\SourceEntityAsserter;
use App\Tests\Services\FileStoreHandler;
use App\Tests\Services\FixtureReader;
use App\Tests\Services\SourceFileInspector;
use App\Tests\Services\UploadedFileFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\YamlFile;

class SourceFactoryTest extends AbstractBaseFunctionalTest
{
    private SourceFactory $factory;
    private FileStoreHandler $localSourceStoreHandler;
    private FileStoreHandler $uploadStoreHandler;
    private UploadedFileFactory $uploadedFileFactory;
    private ManifestFactory $manifestFactory;
    private SourceFileInspector $sourceFileInspector;
    private FixtureReader $fixtureReader;
    private SourceEntityAsserter $sourceEntityAsserter;

    /**
     * @var ObjectRepository<Source>
     */
    private ObjectRepository $sourceRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(SourceFactory::class);
        \assert($factory instanceof SourceFactory);
        $this->factory = $factory;

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

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $sourceRepository = $entityManager->getRepository(Source::class);
        \assert($sourceRepository instanceof ObjectRepository);
        $this->sourceRepository = $sourceRepository;

        $manifestFactory = self::getContainer()->get(ManifestFactory::class);
        \assert($manifestFactory instanceof ManifestFactory);
        $this->manifestFactory = $manifestFactory;

        $sourceFileInspector = self::getContainer()->get(SourceFileInspector::class);
        \assert($sourceFileInspector instanceof SourceFileInspector);
        $this->sourceFileInspector = $sourceFileInspector;

        $fixtureReader = self::getContainer()->get(FixtureReader::class);
        \assert($fixtureReader instanceof FixtureReader);
        $this->fixtureReader = $fixtureReader;

        $sourceEntityAsserter = self::getContainer()->get(SourceEntityAsserter::class);
        \assert($sourceEntityAsserter instanceof SourceEntityAsserter);
        $this->sourceEntityAsserter = $sourceEntityAsserter;
    }

    protected function tearDown(): void
    {
        $this->localSourceStoreHandler->clear();
        $this->uploadStoreHandler->clear();

        parent::tearDown();
    }

    /**
     * @dataProvider createCollectionFromManifestDataProvider
     *
     * @param string[] $fixturePaths
     * @param string[] $expectedStoredTestPaths
     * @param Source[] $expectedSources
     */
    public function testCreateCollectionFromManifest(
        string $manifestPath,
        array $fixturePaths,
        array $expectedStoredTestPaths,
        array $expectedSources
    ): void {
        $manifestUploadedFile = $this->uploadedFileFactory->createForManifest($manifestPath);
        $uploadedSourceFiles = $this->uploadedFileFactory->createCollection(
            $this->uploadStoreHandler->copyFixtures($fixturePaths)
        );

        foreach ($uploadedSourceFiles as $encodedKey => $uploadedFile) {
            unset($uploadedSourceFiles[$encodedKey]);

            $key = UploadedFileKey::fromEncodedKey($encodedKey);
            $uploadedSourceFiles[(string) $key] = $uploadedFile;
        }

        $uploadedSources = new UploadedSourceCollection();
        foreach ($uploadedSourceFiles as $path => $uploadedFile) {
            $uploadedSources[] = new UploadedSource($path, $uploadedFile);
        }

        $manifest = $this->manifestFactory->createFromUploadedFile($manifestUploadedFile);
        self::assertInstanceOf(Manifest::class, $manifest);

        self::assertCount(0, $this->sourceRepository->findAll());

        $this->factory->createCollectionFromManifest($manifest, $uploadedSources);
        foreach ($expectedStoredTestPaths as $expectedStoredTestPath) {
            self::assertTrue($this->sourceFileInspector->has($expectedStoredTestPath));
        }

        $sources = $this->sourceRepository->findAll();
        self::assertCount(count($expectedSources), $sources);

        foreach ($sources as $sourceIndex => $source) {
            $expectedSource = $expectedSources[$sourceIndex];

            self::assertSame($expectedSource->getType(), $source->getType());
            self::assertSame($expectedSource->getPath(), $source->getPath());
        }
    }

    /**
     * @return array<mixed>
     */
    public function createCollectionFromManifestDataProvider(): array
    {
        return [
            'empty manifest' => [
                'manifestPath' => getcwd() . '/tests/Fixtures/Manifest/empty.yml',
                'fixturePaths' => [],
                'expectedStoredTestPaths' => [],
                'expectedSources' => [],
            ],
            'non-empty manifest' => [
                'manifestPath' => getcwd() . '/tests/Fixtures/Manifest/manifest.yml',
                'fixturePaths' => [
                    'Test/chrome-open-index.yml',
                    'Test/chrome-firefox-open-index.yml',
                    'Test/chrome-open-form.yml',
                ],
                'expectedStoredTestPaths' => [
                    'Test/chrome-open-index.yml',
                    'Test/chrome-firefox-open-index.yml',
                    'Test/chrome-open-form.yml',
                ],
                'expectedSources' => [
                    Source::create(Source::TYPE_TEST, 'Test/chrome-open-index.yml'),
                    Source::create(Source::TYPE_TEST, 'Test/chrome-firefox-open-index.yml'),
                    Source::create(Source::TYPE_TEST, 'Test/chrome-open-form.yml'),
                ],
            ],
        ];
    }

    /**
     * @dataProvider createFromYamlSourceCollectionThrowsMissingTestSourceExceptionDataProvider
     */
    public function testCreateFromYamlSourceCollectionThrowsMissingTestSourceException(
        YamlSourceCollection $collection,
        string $expectedMissingTestSourcePath
    ): void {
        try {
            $this->factory->createFromYamlSourceCollection($collection);
            self::fail(MissingTestSourceException::class . ' not thrown');
        } catch (MissingTestSourceException $e) {
            self::assertSame($expectedMissingTestSourcePath, $e->getPath());
        }
    }

    /**
     * @return array<mixed>
     */
    public function createFromYamlSourceCollectionThrowsMissingTestSourceExceptionDataProvider(): array
    {
        return [
            'single source in manifest, no sources' => [
                'collection' => new YamlSourceCollection(
                    new Manifest([
                        'test1.yaml',
                    ]),
                    new ArrayCollection([]),
                ),
                'expectedMissingTestSourcePath' => 'test1.yaml',
            ],
            'two sources in manifest, second not present' => [
                'collection' => new YamlSourceCollection(
                    new Manifest([
                        'test1.yaml',
                        'test2.yaml',
                    ]),
                    new ArrayCollection([
                        YamlFile::create('test1.yaml', ''),
                    ]),
                ),
                'expectedMissingTestSourcePath' => 'test2.yaml',
            ],
        ];
    }

    /**
     * @dataProvider createFromYamlSourceCollectionSuccessDataProvider
     *
     * @param string[] $expectedSourcePaths
     */
    public function testCreateFromYamlSourceCollectionSuccess(
        callable $collectionCreator,
        array $expectedSourcePaths,
    ): void {
        $this->factory->createFromYamlSourceCollection($collectionCreator($this->fixtureReader));

        foreach ($expectedSourcePaths as $expectedSourcePath) {
            self::assertTrue($this->sourceFileInspector->has($expectedSourcePath));
            self::assertSame(
                $this->fixtureReader->read($expectedSourcePath),
                $this->sourceFileInspector->read($expectedSourcePath)
            );

            $this->sourceEntityAsserter->assertRelativePathsEqual($expectedSourcePaths);
        }
    }

    /**
     * @return array<mixed>
     */
    public function createFromYamlSourceCollectionSuccessDataProvider(): array
    {
        return [
            'single test in manifest, single test source' => [
                'collectionCreator' => function (FixtureReader $fixtureReader) {
                    return new YamlSourceCollection(
                        new Manifest([
                            'Test/chrome-open-index.yml',
                        ]),
                        new ArrayCollection([
                            YamlFile::create(
                                'Test/chrome-open-index.yml',
                                $fixtureReader->read('Test/chrome-open-index.yml')
                            ),
                        ]),
                    );
                },
                'expectedSourcePaths' => [
                    'Test/chrome-open-index.yml',
                ],
            ],
            'two tests in manifest, three test sources' => [
                'collectionCreator' => function (FixtureReader $fixtureReader) {
                    return new YamlSourceCollection(
                        new Manifest([
                            'Test/chrome-open-index.yml',
                            'Test/firefox-open-index.yml',
                        ]),
                        new ArrayCollection([
                            YamlFile::create(
                                'Test/chrome-open-index.yml',
                                $fixtureReader->read('Test/chrome-open-index.yml')
                            ),
                            YamlFile::create(
                                'Test/firefox-open-index.yml',
                                $fixtureReader->read('Test/firefox-open-index.yml')
                            ),
                            YamlFile::create(
                                'Page/index.yml',
                                $fixtureReader->read('Page/index.yml')
                            ),
                        ]),
                    );
                },
                'expectedSourcePaths' => [
                    'Test/chrome-open-index.yml',
                    'Test/firefox-open-index.yml',
                    'Page/index.yml',
                ],
            ],
        ];
    }
}
