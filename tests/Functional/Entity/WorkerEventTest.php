<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Tests\Services\EntityRemover;

class WorkerEventTest extends AbstractEntityTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(WorkerEvent::class);
        self::assertCount(0, $repository->findAll());

        $workerEvent = new WorkerEvent(
            WorkerEventScope::COMPILATION,
            WorkerEventOutcome::FAILED,
            'non-empty label',
            'non-empty reference',
            []
        );

        $this->entityManager->persist($workerEvent);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }
}
