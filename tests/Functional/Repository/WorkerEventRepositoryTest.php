<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Repository\WorkerEventRepository;
use App\Tests\Services\EntityRemover;

class WorkerEventRepositoryTest extends AbstractEntityRepositoryTest
{
    private WorkerEventRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($repository instanceof WorkerEventRepository);
        $this->repository = $repository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
        }
    }

    public function testHasForType(): void
    {
        $workerEvent0 = new WorkerEvent(
            WorkerEventScope::COMPILATION,
            WorkerEventOutcome::FAILED,
            'non-empty reference',
            []
        );
        $workerEvent0->setState(WorkerEventState::AWAITING);
        $this->persistEntity($workerEvent0);

        $workerEvent1 = new WorkerEvent(
            WorkerEventScope::TEST,
            WorkerEventOutcome::STARTED,
            'non-empty reference',
            []
        );
        $workerEvent1->setState(WorkerEventState::AWAITING);
        $this->persistEntity($workerEvent1);

        $workerEvent2 = new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::TIME_OUT,
            'non-empty reference',
            []
        );
        $workerEvent2->setState(WorkerEventState::COMPLETE);
        $this->persistEntity($workerEvent2);

        self::assertTrue($this->repository->hasForType(
            WorkerEventScope::COMPILATION,
            WorkerEventOutcome::FAILED
        ));

        self::assertFalse($this->repository->hasForType(
            WorkerEventScope::STEP,
            WorkerEventOutcome::PASSED
        ));
    }

    public function testGetTypeCount(): void
    {
        $this->createWorkerEventsWithTypes([
            [WorkerEventScope::JOB, WorkerEventOutcome::STARTED],
            [WorkerEventScope::STEP, WorkerEventOutcome::PASSED],
            [WorkerEventScope::STEP, WorkerEventOutcome::PASSED],
            [WorkerEventScope::COMPILATION, WorkerEventOutcome::PASSED],
            [WorkerEventScope::COMPILATION, WorkerEventOutcome::PASSED],
            [WorkerEventScope::COMPILATION, WorkerEventOutcome::PASSED],
        ]);

        self::assertSame(
            0,
            $this->repository->getTypeCount(WorkerEventScope::EXECUTION, WorkerEventOutcome::COMPLETED)
        );

        self::assertSame(
            1,
            $this->repository->getTypeCount(WorkerEventScope::JOB, WorkerEventOutcome::STARTED)
        );

        self::assertSame(
            2,
            $this->repository->getTypeCount(WorkerEventScope::STEP, WorkerEventOutcome::PASSED)
        );

        self::assertSame(
            3,
            $this->repository->getTypeCount(WorkerEventScope::COMPILATION, WorkerEventOutcome::PASSED)
        );
    }

    /**
     * @param array<array{0: WorkerEventScope, 1: WorkerEventOutcome}> $types
     */
    private function createWorkerEventsWithTypes(array $types): void
    {
        foreach ($types as $type) {
            $this->repository->add(new WorkerEvent($type[0], $type[1], 'non-empty reference', []));
        }
    }
}
