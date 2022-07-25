<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Repository\WorkerEventRepository;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class WorkerEventRepositoryTest extends AbstractEntityRepositoryTest
{
    private WorkerEventRepository $repository;
    private EnvironmentFactory $environmentFactory;

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

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;
    }

    public function testHasForType(): void
    {
        $this->environmentFactory->create(
            (new EnvironmentSetup())
                ->withWorkerEventSetups([
                    (new WorkerEventSetup())
                        ->withScope(WorkerEventScope::COMPILATION)
                        ->withOutcome(WorkerEventOutcome::FAILED),
                    (new WorkerEventSetup())
                        ->withScope(WorkerEventScope::TEST)
                        ->withOutcome(WorkerEventOutcome::STARTED),
                    (new WorkerEventSetup())
                        ->withScope(WorkerEventScope::JOB)
                        ->withOutcome(WorkerEventOutcome::TIME_OUT),
                ])
        );

        self::assertTrue($this->repository->hasForType(WorkerEventScope::COMPILATION, WorkerEventOutcome::FAILED));
        self::assertFalse($this->repository->hasForType(WorkerEventScope::STEP, WorkerEventOutcome::PASSED));
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
            $this->repository->add(new WorkerEvent($type[0], $type[1], 'non-empty label', 'non-empty reference', []));
        }
    }
}
