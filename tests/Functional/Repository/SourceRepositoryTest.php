<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Source;
use App\Repository\SourceRepository;
use App\Tests\Services\EntityRemover;
use PHPUnit\Framework\Attributes\DataProvider;

class SourceRepositoryTest extends AbstractEntityRepositoryTestCase
{
    private SourceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(SourceRepository::class);
        \assert($repository instanceof SourceRepository);
        $this->repository = $repository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Source::class);
        }
    }

    /**
     * @param Source[]            $sources
     * @param null|Source::TYPE_* $type
     * @param string[]            $expectedPaths
     */
    #[DataProvider('findAllPathsDataProvider')]
    public function testFindAllPaths(array $sources, ?string $type, array $expectedPaths): void
    {
        foreach ($sources as $source) {
            if ($source instanceof Source) {
                $this->entityManager->persist($source);
            }
        }
        $this->entityManager->flush();

        self::assertSame($expectedPaths, $this->repository->findAllPaths($type));
    }

    /**
     * @return array<mixed>
     */
    public static function findAllPathsDataProvider(): array
    {
        return [
            'no sources' => [
                'sources' => [],
                'type' => null,
                'expectedPaths' => [],
            ],
            'test-only sources, type=test' => [
                'sources' => [
                    new Source(Source::TYPE_TEST, 'Test/test1.yml'),
                    new Source(Source::TYPE_TEST, 'Test/test2.yml'),
                ],
                'type' => Source::TYPE_TEST,
                'expectedPaths' => [
                    'Test/test1.yml',
                    'Test/test2.yml',
                ],
            ],
            'test-only sources, type=resource' => [
                'sources' => [
                    new Source(Source::TYPE_TEST, 'Test/test1.yml'),
                ],
                'type' => Source::TYPE_RESOURCE,
                'expectedPaths' => [],
            ],
            'resource-only sources, type=resource' => [
                'sources' => [
                    new Source(Source::TYPE_RESOURCE, 'Page/page1.yml'),
                    new Source(Source::TYPE_RESOURCE, 'Page/page2.yml'),
                ],
                'type' => Source::TYPE_RESOURCE,
                'expectedPaths' => [
                    'Page/page1.yml',
                    'Page/page2.yml',
                ],
            ],
            'resource-only sources, type=test' => [
                'sources' => [
                    new Source(Source::TYPE_RESOURCE, 'Page/page1.yml'),
                ],
                'type' => Source::TYPE_TEST,
                'expectedPaths' => [],
            ],
            'mixed-type sources, type=null' => [
                'sources' => [
                    new Source(Source::TYPE_RESOURCE, 'Page/page1.yml'),
                    new Source(Source::TYPE_TEST, 'Test/test1.yml'),
                    new Source(Source::TYPE_RESOURCE, 'Page/page2.yml'),
                    new Source(Source::TYPE_TEST, 'Test/test2.yml'),
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
                    new Source(Source::TYPE_RESOURCE, 'Page/page1.yml'),
                    new Source(Source::TYPE_TEST, 'Test/test1.yml'),
                    new Source(Source::TYPE_RESOURCE, 'Page/page2.yml'),
                    new Source(Source::TYPE_TEST, 'Test/test2.yml'),
                ],
                'type' => Source::TYPE_TEST,
                'expectedPaths' => [
                    'Test/test1.yml',
                    'Test/test2.yml',
                ],
            ],
            'mixed-type sources, type=resource' => [
                'sources' => [
                    new Source(Source::TYPE_RESOURCE, 'Page/page1.yml'),
                    new Source(Source::TYPE_TEST, 'Test/test1.yml'),
                    new Source(Source::TYPE_RESOURCE, 'Page/page2.yml'),
                    new Source(Source::TYPE_TEST, 'Test/test2.yml'),
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
