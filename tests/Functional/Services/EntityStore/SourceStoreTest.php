<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EntityStore;

use App\Entity\Source;
use App\Services\EntityPersister;
use App\Services\EntityStore\SourceStore;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\EntityRemover;

class SourceStoreTest extends AbstractBaseFunctionalTest
{
    private SourceStore $store;
    private EntityPersister $entityPersister;

    protected function setUp(): void
    {
        parent::setUp();

        $store = self::getContainer()->get(SourceStore::class);
        \assert($store instanceof SourceStore);
        $this->store = $store;

        $entityPersister = self::getContainer()->get(EntityPersister::class);
        \assert($entityPersister instanceof EntityPersister);
        $this->entityPersister = $entityPersister;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Source::class);
        }
    }

    /**
     * @dataProvider findAllPathsDataProvider
     *
     * @param Source[]            $sources
     * @param null|Source::TYPE_* $type
     * @param string[]            $expectedPaths
     */
    public function testFindAllPaths(array $sources, ?string $type, array $expectedPaths): void
    {
        foreach ($sources as $source) {
            if ($source instanceof Source) {
                $this->entityPersister->persist($source);
            }
        }

        self::assertSame($expectedPaths, $this->store->findAllPaths($type));
    }

    /**
     * @return array<mixed>
     */
    public function findAllPathsDataProvider(): array
    {
        return [
            'no sources' => [
                'sources' => [],
                'type' => null,
                'expectedPaths' => [],
            ],
            'test-only sources, type=test' => [
                'sources' => [
                    Source::create(Source::TYPE_TEST, 'Test/test1.yml'),
                    Source::create(Source::TYPE_TEST, 'Test/test2.yml'),
                ],
                'type' => Source::TYPE_TEST,
                'expectedPaths' => [
                    'Test/test1.yml',
                    'Test/test2.yml',
                ],
            ],
            'test-only sources, type=resource' => [
                'sources' => [
                    Source::create(Source::TYPE_TEST, 'Test/test1.yml'),
                ],
                'type' => Source::TYPE_RESOURCE,
                'expectedPaths' => [],
            ],
            'resource-only sources, type=resource' => [
                'sources' => [
                    Source::create(Source::TYPE_RESOURCE, 'Page/page1.yml'),
                    Source::create(Source::TYPE_RESOURCE, 'Page/page2.yml'),
                ],
                'type' => Source::TYPE_RESOURCE,
                'expectedPaths' => [
                    'Page/page1.yml',
                    'Page/page2.yml',
                ],
            ],
            'resource-only sources, type=test' => [
                'sources' => [
                    Source::create(Source::TYPE_RESOURCE, 'Page/page1.yml'),
                ],
                'type' => Source::TYPE_TEST,
                'expectedPaths' => [],
            ],
            'mixed-type sources, type=null' => [
                'sources' => [
                    Source::create(Source::TYPE_RESOURCE, 'Page/page1.yml'),
                    Source::create(Source::TYPE_TEST, 'Test/test1.yml'),
                    Source::create(Source::TYPE_RESOURCE, 'Page/page2.yml'),
                    Source::create(Source::TYPE_TEST, 'Test/test2.yml'),
                ],
                'type' => null,
                'expectedPaths' => [
                    'Page/page1.yml',
                    'Test/test1.yml',
                    'Page/page2.yml',
                    'Test/test2.yml',
                ],
            ],
            'mixed-type sources, type=test' => [
                'sources' => [
                    Source::create(Source::TYPE_RESOURCE, 'Page/page1.yml'),
                    Source::create(Source::TYPE_TEST, 'Test/test1.yml'),
                    Source::create(Source::TYPE_RESOURCE, 'Page/page2.yml'),
                    Source::create(Source::TYPE_TEST, 'Test/test2.yml'),
                ],
                'type' => Source::TYPE_TEST,
                'expectedPaths' => [
                    'Test/test1.yml',
                    'Test/test2.yml',
                ],
            ],
            'mixed-type sources, type=resource' => [
                'sources' => [
                    Source::create(Source::TYPE_RESOURCE, 'Page/page1.yml'),
                    Source::create(Source::TYPE_TEST, 'Test/test1.yml'),
                    Source::create(Source::TYPE_RESOURCE, 'Page/page2.yml'),
                    Source::create(Source::TYPE_TEST, 'Test/test2.yml'),
                ],
                'type' => Source::TYPE_RESOURCE,
                'expectedPaths' => [
                    'Page/page1.yml',
                    'Page/page2.yml',
                ],
            ],
        ];
    }
}
