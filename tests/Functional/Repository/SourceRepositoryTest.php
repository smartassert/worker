<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\EntityInterface;
use App\Entity\Source;
use App\Repository\SourceRepository;
use App\Tests\Services\EntityRemover;

class SourceRepositoryTest extends AbstractEntityRepositoryTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Source::class);
        }
    }

    public function findOneByDataProvider(): array
    {
        return [
            'type test' => [
                'criteria' => [
                    'type' => Source::TYPE_TEST,
                ],
                'orderBy' => null,
                'expectedEntityIndex' => 0,
            ],
            'type resource' => [
                'criteria' => [
                    'type' => Source::TYPE_RESOURCE,
                ],
                'orderBy' => null,
                'expectedEntityIndex' => 2,
            ],
            'path Test/test2.yml' => [
                'criteria' => [
                    'path' => 'Test/test2.yml',
                ],
                'orderBy' => null,
                'expectedEntityIndex' => 1,
            ],
            'type test and path Test/test2.yml' => [
                'criteria' => [
                    'type' => Source::TYPE_TEST,
                    'path' => 'Test/test2.yml',
                ],
                'orderBy' => null,
                'expectedEntityIndex' => 1,
            ],
            'invalid type' => [
                'criteria' => [
                    'type' => 'Invalid',
                ],
                'orderBy' => null,
                'expectedEntityIndex' => null,
            ],
        ];
    }

    public function countDataProvider(): array
    {
        return [
            'type test' => [
                'criteria' => [
                    'type' => Source::TYPE_TEST,
                ],
                'expectedCount' => 2,
            ],
            'type resource' => [
                'criteria' => [
                    'type' => Source::TYPE_RESOURCE,
                ],
                'expectedCount' => 1,
            ],
            'path Test/test2.yml' => [
                'criteria' => [
                    'path' => 'Test/test2.yml',
                ],
                'expectedCount' => 1,
            ],
            'type test and path Test/test2.yml' => [
                'criteria' => [
                    'type' => Source::TYPE_TEST,
                    'path' => 'Test/test2.yml',
                ],
                'expectedCount' => 1,
            ],
            'invalid type' => [
                'criteria' => [
                    'type' => 'Invalid',
                ],
                'expectedCount' => 0,
            ],
        ];
    }

    protected function getRepository(): ?SourceRepository
    {
        $repository = self::getContainer()->get(SourceRepository::class);
        if ($repository instanceof SourceRepository) {
            return $repository;
        }

        return null;
    }

    protected function createSingleEntity(): EntityInterface
    {
        return Source::create(Source::TYPE_TEST, 'Test/test.yml');
    }

    protected function createEntityCollection(): array
    {
        return [
            Source::create(Source::TYPE_TEST, 'Test/test1.yml'),
            Source::create(Source::TYPE_TEST, 'Test/test2.yml'),
            Source::create(Source::TYPE_RESOURCE, 'Page/page.yml'),
        ];
    }
}
