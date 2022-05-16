<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventState;
use App\Services\EventDeliveryProgress;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestWorkerEventFactory;

class EventDeliveryProgressTest extends AbstractBaseFunctionalTest
{
    private EventDeliveryProgress $eventDeliveryProgress;
    private TestWorkerEventFactory $testWorkerEventFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDeliveryProgress = self::getContainer()->get(EventDeliveryProgress::class);
        \assert($eventDeliveryProgress instanceof EventDeliveryProgress);
        $this->eventDeliveryProgress = $eventDeliveryProgress;

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

        self::assertSame($expectedState, $this->eventDeliveryProgress->get());
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'no events' => [
                'states' => [],
                'expectedState' => EventDeliveryProgress::STATE_AWAITING,
            ],
            'awaiting, sending, queued' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                ],
                'expectedState' => EventDeliveryProgress::STATE_RUNNING,
            ],
            'awaiting, sending, queued, complete' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                    WorkerEventState::COMPLETE,
                ],
                'expectedState' => EventDeliveryProgress::STATE_RUNNING,
            ],
            'awaiting, sending, queued, failed' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                    WorkerEventState::FAILED,
                ],
                'expectedState' => EventDeliveryProgress::STATE_RUNNING,
            ],
            'two complete, three failed' => [
                'states' => [
                    WorkerEventState::COMPLETE,
                    WorkerEventState::COMPLETE,
                    WorkerEventState::FAILED,
                    WorkerEventState::FAILED,
                    WorkerEventState::FAILED,
                ],
                'expectedState' => EventDeliveryProgress::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param WorkerEventState[]                    $states
     * @param array<EventDeliveryProgress::STATE_*> $expectedIsStates
     * @param array<EventDeliveryProgress::STATE_*> $expectedIsNotStates
     */
    public function testIs(array $states, array $expectedIsStates, array $expectedIsNotStates): void
    {
        foreach ($states as $state) {
            $this->createWorkerEventEntity($state);
        }

        self::assertTrue($this->eventDeliveryProgress->is(...$expectedIsStates));
        self::assertFalse($this->eventDeliveryProgress->is(...$expectedIsNotStates));
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
                    EventDeliveryProgress::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryProgress::STATE_RUNNING,
                    EventDeliveryProgress::STATE_COMPLETE,
                ],
            ],
            'awaiting, sending, queued' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                ],
                'expectedIsStates' => [
                    EventDeliveryProgress::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryProgress::STATE_AWAITING,
                    EventDeliveryProgress::STATE_COMPLETE,
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
                    EventDeliveryProgress::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryProgress::STATE_AWAITING,
                    EventDeliveryProgress::STATE_COMPLETE,
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
                    EventDeliveryProgress::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryProgress::STATE_AWAITING,
                    EventDeliveryProgress::STATE_COMPLETE,
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
                    EventDeliveryProgress::STATE_COMPLETE,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryProgress::STATE_AWAITING,
                    EventDeliveryProgress::STATE_RUNNING,
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
