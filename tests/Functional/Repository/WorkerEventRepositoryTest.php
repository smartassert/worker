<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Enum\WorkerEventType;
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
            WorkerEventType::COMPILATION_FAILED,
            'non-empty reference',
            []
        );

        $workerEvent0->setState(WorkerEventState::AWAITING);
        $this->persistEntity($workerEvent0);

        $workerEvent1 = new WorkerEvent(
            WorkerEventScope::TEST,
            WorkerEventOutcome::STARTED,
            WorkerEventType::TEST_STARTED,
            'non-empty reference',
            []
        );

        $workerEvent1->setState(WorkerEventState::AWAITING);
        $this->persistEntity($workerEvent1);

        $workerEvent2 = new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::TIME_OUT,
            WorkerEventType::JOB_TIME_OUT,
            'non-empty reference',
            []
        );

        $workerEvent2->setState(WorkerEventState::COMPLETE);
        $this->persistEntity($workerEvent2);

        self::assertTrue($this->repository->hasForType(WorkerEventType::COMPILATION_FAILED));
        self::assertFalse($this->repository->hasForType(WorkerEventType::STEP_PASSED));
    }

    public function testGetTypeCount(): void
    {
        $this->createWorkerEventsWithTypes([
            WorkerEventType::JOB_STARTED,
            WorkerEventType::STEP_PASSED,
            WorkerEventType::STEP_PASSED,
            WorkerEventType::COMPILATION_PASSED,
            WorkerEventType::COMPILATION_PASSED,
            WorkerEventType::COMPILATION_PASSED,
        ]);

        self::assertSame(0, $this->repository->getTypeCount(WorkerEventType::EXECUTION_COMPLETED));
        self::assertSame(1, $this->repository->getTypeCount(WorkerEventType::JOB_STARTED));
        self::assertSame(2, $this->repository->getTypeCount(WorkerEventType::STEP_PASSED));
        self::assertSame(3, $this->repository->getTypeCount(WorkerEventType::COMPILATION_PASSED));
    }

    /**
     * @param array<WorkerEventType::*> $types
     */
    private function createWorkerEventsWithTypes(array $types): void
    {
        foreach ($types as $type) {
            $this->repository->add(new WorkerEvent(
                WorkerEventScope::UNKNOWN,
                WorkerEventOutcome::UNKNOWN,
                $type,
                'non-empty reference',
                []
            ));
        }
    }
}
