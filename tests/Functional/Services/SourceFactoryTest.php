<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Exception\MissingTestSourceException;
use App\Model\Manifest;
use App\Model\YamlSourceCollection;
use App\Services\EntityStore\SourceStore;
use App\Services\SourceFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\FileStoreHandler;
use App\Tests\Services\FixtureReader;
use App\Tests\Services\SourceFileInspector;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\YamlFile;

class SourceFactoryTest extends AbstractBaseFunctionalTest
{
    private SourceFactory $factory;
    private FileStoreHandler $localSourceStoreHandler;
    private SourceFileInspector $sourceFileInspector;
    private FixtureReader $fixtureReader;
    private SourceStore $sourceStore;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(SourceFactory::class);
        \assert($factory instanceof SourceFactory);
        $this->factory = $factory;

        $localSourceStoreHandler = self::getContainer()->get('app.tests.services.file_store_handler.local_source');
        \assert($localSourceStoreHandler instanceof FileStoreHandler);
        $this->localSourceStoreHandler = $localSourceStoreHandler;
        $this->localSourceStoreHandler->clear();

        $sourceFileInspector = self::getContainer()->get(SourceFileInspector::class);
        \assert($sourceFileInspector instanceof SourceFileInspector);
        $this->sourceFileInspector = $sourceFileInspector;

        $fixtureReader = self::getContainer()->get(FixtureReader::class);
        \assert($fixtureReader instanceof FixtureReader);
        $this->fixtureReader = $fixtureReader;

        $sourceStore = self::getContainer()->get(SourceStore::class);
        \assert($sourceStore instanceof SourceStore);
        $this->sourceStore = $sourceStore;
    }

    protected function tearDown(): void
    {
        $this->localSourceStoreHandler->clear();

        parent::tearDown();
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

            self::assertSame($expectedSourcePaths, $this->sourceStore->findAllPaths());
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
