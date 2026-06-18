<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
use App\Repository\WorkerEventRepository;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class WorkerEventRepositoryTest extends AbstractEntityRepositoryTestCase
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
            new EnvironmentSetup()
                ->withWorkerEventSetups([
                    new WorkerEventSetup()
                        ->withScope(WorkerEventScope::SOURCE_COMPILATION)
                        ->withOutcome(WorkerEventOutcome::FAILED)
                        ->withType(WorkerEventType::SOURCE_COMPILATION_FAILED),
                    new WorkerEventSetup()
                        ->withScope(WorkerEventScope::TEST)
                        ->withOutcome(WorkerEventOutcome::STARTED)
                        ->withType(WorkerEventType::TEST_STARTED),
                    new WorkerEventSetup()
                        ->withScope(WorkerEventScope::JOB)
                        ->withOutcome(WorkerEventOutcome::TIME_OUT)
                        ->withType(WorkerEventType::JOB_TIMED_OUT),
                ])
        );

        self::assertTrue($this->repository->hasForType(WorkerEventType::SOURCE_COMPILATION_FAILED));
        self::assertFalse($this->repository->hasForType(WorkerEventType::STEP_PASSED));
    }

    public function testGetTypeCount(): void
    {
        $this->environmentFactory->create(
            new EnvironmentSetup()
                ->withWorkerEventSetups([
                    new WorkerEventSetup()
                        ->withScope(WorkerEventScope::JOB)
                        ->withOutcome(WorkerEventOutcome::STARTED)
                        ->withType(WorkerEventType::JOB_STARTED),
                    new WorkerEventSetup()
                        ->withScope(WorkerEventScope::STEP)
                        ->withOutcome(WorkerEventOutcome::PASSED)
                        ->withType(WorkerEventType::STEP_PASSED),
                    new WorkerEventSetup()
                        ->withScope(WorkerEventScope::STEP)
                        ->withOutcome(WorkerEventOutcome::PASSED)
                        ->withType(WorkerEventType::STEP_PASSED),
                    new WorkerEventSetup()
                        ->withScope(WorkerEventScope::SOURCE_COMPILATION)
                        ->withOutcome(WorkerEventOutcome::PASSED)
                        ->withType(WorkerEventType::SOURCE_COMPILATION_PASSED),
                    new WorkerEventSetup()
                        ->withScope(WorkerEventScope::SOURCE_COMPILATION)
                        ->withOutcome(WorkerEventOutcome::PASSED)
                        ->withType(WorkerEventType::SOURCE_COMPILATION_PASSED),
                    new WorkerEventSetup()
                        ->withScope(WorkerEventScope::SOURCE_COMPILATION)
                        ->withOutcome(WorkerEventOutcome::PASSED)
                        ->withType(WorkerEventType::SOURCE_COMPILATION_PASSED),
                ])
        );

        self::assertSame(
            0,
            $this->repository->getTypeCount(WorkerEventType::JOB_EXECUTION_COMPLETED)
        );

        self::assertSame(
            1,
            $this->repository->getTypeCount(WorkerEventType::JOB_STARTED)
        );

        self::assertSame(
            2,
            $this->repository->getTypeCount(WorkerEventType::STEP_PASSED)
        );

        self::assertSame(
            3,
            $this->repository->getTypeCount(WorkerEventType::SOURCE_COMPILATION_PASSED)
        );
    }

    public function testFindAllIdsNoEvents(): void
    {
        self::assertSame([], $this->repository->findAllIds());
    }

    public function testFindAllIdsHasEvents(): void
    {
        $workerEventSetups = [];
        $eventCount = 10;

        for ($i = 0; $i <= $eventCount; ++$i) {
            $workerEventSetups[] = new WorkerEventSetup();
        }

        $environment = $this->environmentFactory->create(
            new EnvironmentSetup()
                ->withWorkerEventSetups($workerEventSetups)
        );

        $eventIds = [];
        foreach ($environment->getWorkerEvents() as $event) {
            $eventIds[] = (int) $event->getId();
        }

        self::assertNotEmpty($eventIds);
        self::assertSame($eventIds, $this->repository->findAllIds());
    }
}
