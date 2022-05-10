<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState;
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
        $workerEvent0 = WorkerEvent::create(
            WorkerEvent::TYPE_COMPILATION_FAILED,
            'non-empty reference',
            []
        )
        ;
        $workerEvent0->setState(WorkerEventState::AWAITING);
        $this->persistEntity($workerEvent0);

        $workerEvent1 = WorkerEvent::create(
            WorkerEvent::TYPE_TEST_STARTED,
            'non-empty reference',
            []
        );
        $workerEvent1->setState(WorkerEventState::AWAITING);
        $this->persistEntity($workerEvent1);

        $workerEvent2 = WorkerEvent::create(
            WorkerEvent::TYPE_JOB_TIME_OUT,
            'non-empty reference',
            []
        );
        $workerEvent2->setState(WorkerEventState::COMPLETE);
        $this->persistEntity($workerEvent2);

        self::assertTrue($this->repository->hasForType(WorkerEvent::TYPE_COMPILATION_FAILED));
        self::assertFalse($this->repository->hasForType(WorkerEvent::TYPE_STEP_PASSED));
    }

    public function testGetTypeCount(): void
    {
        $this->createWorkerEventsWithTypes([
            WorkerEvent::TYPE_JOB_STARTED,
            WorkerEvent::TYPE_STEP_PASSED,
            WorkerEvent::TYPE_STEP_PASSED,
            WorkerEvent::TYPE_COMPILATION_PASSED,
            WorkerEvent::TYPE_COMPILATION_PASSED,
            WorkerEvent::TYPE_COMPILATION_PASSED,
        ]);

        self::assertSame(0, $this->repository->getTypeCount(WorkerEvent::TYPE_EXECUTION_COMPLETED));
        self::assertSame(1, $this->repository->getTypeCount(WorkerEvent::TYPE_JOB_STARTED));
        self::assertSame(2, $this->repository->getTypeCount(WorkerEvent::TYPE_STEP_PASSED));
        self::assertSame(3, $this->repository->getTypeCount(WorkerEvent::TYPE_COMPILATION_PASSED));
    }

    /**
     * @param array<WorkerEvent::TYPE_*> $types
     */
    private function createWorkerEventsWithTypes(array $types): void
    {
        foreach ($types as $type) {
            $this->repository->create($type, 'non-empty reference', []);
        }
    }
}
