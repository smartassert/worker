<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState as EntityState;
use App\Services\WorkerEventState;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestWorkerEventFactory;

class WorkerEventStateTest extends AbstractBaseFunctionalTest
{
    private WorkerEventState $workerEventState;
    private TestWorkerEventFactory $testWorkerEventFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $workerEventState = self::getContainer()->get(WorkerEventState::class);
        if ($workerEventState instanceof WorkerEventState) {
            $this->workerEventState = $workerEventState;
        }

        $testWorkerEventFactory = self::getContainer()->get(TestWorkerEventFactory::class);
        \assert($testWorkerEventFactory instanceof TestWorkerEventFactory);
        $this->testWorkerEventFactory = $testWorkerEventFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
        }
    }

    /**
     * @dataProvider getDataProvider
     *
     * @param EntityState[] $states
     */
    public function testGet(array $states, string $expectedState): void
    {
        foreach ($states as $workerEventState) {
            $this->createWorkerEventEntity($workerEventState);
        }

        self::assertSame($expectedState, (string) $this->workerEventState);
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'no events' => [
                'states' => [],
                'expectedState' => WorkerEventState::STATE_AWAITING,
            ],
            'awaiting, sending, queued' => [
                'states' => [
                    EntityState::AWAITING,
                    EntityState::QUEUED,
                    EntityState::SENDING,
                ],
                'expectedState' => WorkerEventState::STATE_RUNNING,
            ],
            'awaiting, sending, queued, complete' => [
                'states' => [
                    EntityState::AWAITING,
                    EntityState::QUEUED,
                    EntityState::SENDING,
                    EntityState::COMPLETE,
                ],
                'expectedState' => WorkerEventState::STATE_RUNNING,
            ],
            'awaiting, sending, queued, failed' => [
                'states' => [
                    EntityState::AWAITING,
                    EntityState::QUEUED,
                    EntityState::SENDING,
                    EntityState::FAILED,
                ],
                'expectedState' => WorkerEventState::STATE_RUNNING,
            ],
            'two complete, three failed' => [
                'states' => [
                    EntityState::COMPLETE,
                    EntityState::COMPLETE,
                    EntityState::FAILED,
                    EntityState::FAILED,
                    EntityState::FAILED,
                ],
                'expectedState' => WorkerEventState::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param EntityState[]                    $states
     * @param array<WorkerEventState::STATE_*> $expectedIsStates
     * @param array<WorkerEventState::STATE_*> $expectedIsNotStates
     */
    public function testIs(array $states, array $expectedIsStates, array $expectedIsNotStates): void
    {
        foreach ($states as $state) {
            $this->createWorkerEventEntity($state);
        }

        self::assertTrue($this->workerEventState->is(...$expectedIsStates));
        self::assertFalse($this->workerEventState->is(...$expectedIsNotStates));
    }

    /**
     * @return array<mixed>
     */
    public function isDataProvider(): array
    {
        return [
            'no callbacks' => [
                'states' => [],
                'expectedIsStates' => [
                    WorkerEventState::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    WorkerEventState::STATE_RUNNING,
                    WorkerEventState::STATE_COMPLETE,
                ],
            ],
            'awaiting, sending, queued' => [
                'states' => [
                    EntityState::AWAITING,
                    EntityState::QUEUED,
                    EntityState::SENDING,
                ],
                'expectedIsStates' => [
                    WorkerEventState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    WorkerEventState::STATE_AWAITING,
                    WorkerEventState::STATE_COMPLETE,
                ],
            ],
            'awaiting, sending, queued, complete' => [
                'states' => [
                    EntityState::AWAITING,
                    EntityState::QUEUED,
                    EntityState::SENDING,
                    EntityState::COMPLETE,
                ],
                'expectedIsStates' => [
                    WorkerEventState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    WorkerEventState::STATE_AWAITING,
                    WorkerEventState::STATE_COMPLETE,
                ],
            ],
            'awaiting, sending, queued, failed' => [
                'states' => [
                    EntityState::AWAITING,
                    EntityState::QUEUED,
                    EntityState::SENDING,
                    EntityState::FAILED,
                ],
                'expectedIsStates' => [
                    WorkerEventState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    WorkerEventState::STATE_AWAITING,
                    WorkerEventState::STATE_COMPLETE,
                ],
            ],
            'two complete, three failed' => [
                'states' => [
                    EntityState::COMPLETE,
                    EntityState::COMPLETE,
                    EntityState::FAILED,
                    EntityState::FAILED,
                    EntityState::FAILED,
                ],
                'expectedIsStates' => [
                    WorkerEventState::STATE_COMPLETE,
                ],
                'expectedIsNotStates' => [
                    WorkerEventState::STATE_AWAITING,
                    WorkerEventState::STATE_RUNNING,
                ],
            ],
        ];
    }

    private function createWorkerEventEntity(EntityState $state): void
    {
        $this->testWorkerEventFactory->create(
            (new WorkerEventSetup())->withState($state)
        );
    }
}
