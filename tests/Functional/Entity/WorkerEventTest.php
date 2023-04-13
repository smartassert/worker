<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Tests\Services\EntityRemover;
use SmartAssert\ResultsClient\Model\ResourceReferenceCollection;

class WorkerEventTest extends AbstractEntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(WorkerEventReference::class);
        }
    }

    /**
     * @dataProvider entityMappingDataProvider
     */
    public function testEntityMapping(WorkerEvent $event): void
    {
        $repository = $this->entityManager->getRepository(WorkerEvent::class);
        self::assertCount(0, $repository->findAll());

        $this->entityManager->persist($event->reference);
        $this->entityManager->persist($event);

        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }

    /**
     * @return array<mixed>
     */
    public function entityMappingDataProvider(): array
    {
        return [
            'without related references' => [
                'event' => new WorkerEvent(
                    WorkerEventScope::SOURCE_COMPILATION,
                    WorkerEventOutcome::FAILED,
                    new WorkerEventReference('non-empty label', 'non-empty reference'),
                    []
                ),
            ],
            'with related references' => [
                'event' => (new WorkerEvent(
                    WorkerEventScope::SOURCE_COMPILATION,
                    WorkerEventOutcome::FAILED,
                    new WorkerEventReference('non-empty label', 'non-empty reference'),
                    [],
                ))->withRelatedReferences(
                    new ResourceReferenceCollection([
                        new WorkerEventReference('label 1', 'reference 1'),
                        new WorkerEventReference('label 2', 'reference 2'),
                        new WorkerEventReference('label 3', 'reference 3'),
                    ])
                ),
            ],
        ];
    }
}
