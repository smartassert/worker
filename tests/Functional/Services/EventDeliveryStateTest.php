<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState;
use App\Services\EventDeliveryState;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestWorkerEventFactory;

class EventDeliveryStateTest extends AbstractBaseFunctionalTest
{
    private EventDeliveryState $workerEventState;
    private TestWorkerEventFactory $testWorkerEventFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $workerEventState = self::getContainer()->get(EventDeliveryState::class);
        if ($workerEventState instanceof EventDeliveryState) {
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
     * @param WorkerEventState[] $states
     */
    public function testGet(array $states, string $expectedState): void
    {
        foreach ($states as $workerEventState) {
            $this->createWorkerEventEntity($workerEventState);
        }

        self::assertSame($expectedState, $this->workerEventState->get());
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'no events' => [
                'states' => [],
                'expectedState' => EventDeliveryState::STATE_AWAITING,
            ],
            'awaiting, sending, queued' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                ],
                'expectedState' => EventDeliveryState::STATE_RUNNING,
            ],
            'awaiting, sending, queued, complete' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                    WorkerEventState::COMPLETE,
                ],
                'expectedState' => EventDeliveryState::STATE_RUNNING,
            ],
            'awaiting, sending, queued, failed' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                    WorkerEventState::FAILED,
                ],
                'expectedState' => EventDeliveryState::STATE_RUNNING,
            ],
            'two complete, three failed' => [
                'states' => [
                    WorkerEventState::COMPLETE,
                    WorkerEventState::COMPLETE,
                    WorkerEventState::FAILED,
                    WorkerEventState::FAILED,
                    WorkerEventState::FAILED,
                ],
                'expectedState' => EventDeliveryState::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param WorkerEventState[]                 $states
     * @param array<EventDeliveryState::STATE_*> $expectedIsStates
     * @param array<EventDeliveryState::STATE_*> $expectedIsNotStates
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
            'no event deliveries' => [
                'states' => [],
                'expectedIsStates' => [
                    EventDeliveryState::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryState::STATE_RUNNING,
                    EventDeliveryState::STATE_COMPLETE,
                ],
            ],
            'awaiting, sending, queued' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                ],
                'expectedIsStates' => [
                    EventDeliveryState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryState::STATE_AWAITING,
                    EventDeliveryState::STATE_COMPLETE,
                ],
            ],
            'awaiting, sending, queued, complete' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                    WorkerEventState::COMPLETE,
                ],
                'expectedIsStates' => [
                    EventDeliveryState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryState::STATE_AWAITING,
                    EventDeliveryState::STATE_COMPLETE,
                ],
            ],
            'awaiting, sending, queued, failed' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                    WorkerEventState::FAILED,
                ],
                'expectedIsStates' => [
                    EventDeliveryState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryState::STATE_AWAITING,
                    EventDeliveryState::STATE_COMPLETE,
                ],
            ],
            'two complete, three failed' => [
                'states' => [
                    WorkerEventState::COMPLETE,
                    WorkerEventState::COMPLETE,
                    WorkerEventState::FAILED,
                    WorkerEventState::FAILED,
                    WorkerEventState::FAILED,
                ],
                'expectedIsStates' => [
                    EventDeliveryState::STATE_COMPLETE,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryState::STATE_AWAITING,
                    EventDeliveryState::STATE_RUNNING,
                ],
            ],
        ];
    }

    private function createWorkerEventEntity(WorkerEventState $state): void
    {
        $this->testWorkerEventFactory->create(
            (new WorkerEventSetup())->withState($state)
        );
    }
}
