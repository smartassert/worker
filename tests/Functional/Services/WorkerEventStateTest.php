<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Services\WorkerEventState;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\CallbackSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestCallbackFactory;

class WorkerEventStateTest extends AbstractBaseFunctionalTest
{
    private WorkerEventState $workerEventState;
    private TestCallbackFactory $testCallbackFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $workerEventState = self::getContainer()->get(WorkerEventState::class);
        if ($workerEventState instanceof WorkerEventState) {
            $this->workerEventState = $workerEventState;
        }

        $testCallbackFactory = self::getContainer()->get(TestCallbackFactory::class);
        \assert($testCallbackFactory instanceof TestCallbackFactory);
        $this->testCallbackFactory = $testCallbackFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
        }
    }

    /**
     * @dataProvider getDataProvider
     *
     * @param array<WorkerEvent::STATE_*> $states
     */
    public function testGet(array $states, string $expectedState): void
    {
        foreach ($states as $callbackState) {
            $this->createWorkerEventEntity($callbackState);
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
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                ],
                'expectedState' => WorkerEventState::STATE_RUNNING,
            ],
            'awaiting, sending, queued, complete' => [
                'states' => [
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                    WorkerEvent::STATE_COMPLETE,
                ],
                'expectedState' => WorkerEventState::STATE_RUNNING,
            ],
            'awaiting, sending, queued, failed' => [
                'states' => [
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                    WorkerEvent::STATE_FAILED,
                ],
                'expectedState' => WorkerEventState::STATE_RUNNING,
            ],
            'two complete, three failed' => [
                'states' => [
                    WorkerEvent::STATE_COMPLETE,
                    WorkerEvent::STATE_COMPLETE,
                    WorkerEvent::STATE_FAILED,
                    WorkerEvent::STATE_FAILED,
                    WorkerEvent::STATE_FAILED,
                ],
                'expectedState' => WorkerEventState::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param array<WorkerEvent::STATE_*>      $states
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
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
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
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                    WorkerEvent::STATE_COMPLETE,
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
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                    WorkerEvent::STATE_FAILED,
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
                    WorkerEvent::STATE_COMPLETE,
                    WorkerEvent::STATE_COMPLETE,
                    WorkerEvent::STATE_FAILED,
                    WorkerEvent::STATE_FAILED,
                    WorkerEvent::STATE_FAILED,
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

    /**
     * @param WorkerEvent::STATE_* $state
     */
    private function createWorkerEventEntity(string $state): void
    {
        $this->testCallbackFactory->create(
            (new CallbackSetup())->withState($state)
        );
    }
}
