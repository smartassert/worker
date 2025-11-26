<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Enum\EventDeliveryState;
use App\Enum\WorkerEventState;
use App\Services\EventDeliveryProgress;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestWorkerEventFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventDeliveryProgressTest extends WebTestCase
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
     * @param WorkerEventState[] $states
     */
    #[DataProvider('getDataProvider')]
    public function testGet(array $states, EventDeliveryState $expectedState): void
    {
        foreach ($states as $workerEventState) {
            $this->createWorkerEventEntity($workerEventState);
        }

        self::assertSame($expectedState, $this->eventDeliveryProgress->get());
    }

    /**
     * @return array<mixed>
     */
    public static function getDataProvider(): array
    {
        return [
            'no events' => [
                'states' => [],
                'expectedState' => EventDeliveryState::AWAITING,
            ],
            'awaiting, sending, queued' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                ],
                'expectedState' => EventDeliveryState::RUNNING,
            ],
            'awaiting, sending, queued, complete' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                    WorkerEventState::COMPLETE,
                ],
                'expectedState' => EventDeliveryState::RUNNING,
            ],
            'awaiting, sending, queued, failed' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                    WorkerEventState::FAILED,
                ],
                'expectedState' => EventDeliveryState::RUNNING,
            ],
            'two complete, three failed' => [
                'states' => [
                    WorkerEventState::COMPLETE,
                    WorkerEventState::COMPLETE,
                    WorkerEventState::FAILED,
                    WorkerEventState::FAILED,
                    WorkerEventState::FAILED,
                ],
                'expectedState' => EventDeliveryState::COMPLETE,
            ],
        ];
    }

    /**
     * @param WorkerEventState[]   $states
     * @param EventDeliveryState[] $expectedIsStates
     * @param EventDeliveryState[] $expectedIsNotStates
     */
    #[DataProvider('isDataProvider')]
    public function testIs(array $states, array $expectedIsStates, array $expectedIsNotStates): void
    {
        foreach ($states as $state) {
            $this->createWorkerEventEntity($state);
        }

        self::assertContains($this->eventDeliveryProgress->get(), $expectedIsStates);
        self::assertNotContains($this->eventDeliveryProgress->get(), $expectedIsNotStates);
    }

    /**
     * @return array<mixed>
     */
    public static function isDataProvider(): array
    {
        return [
            'no event deliveries' => [
                'states' => [],
                'expectedIsStates' => [
                    EventDeliveryState::AWAITING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryState::RUNNING,
                    EventDeliveryState::COMPLETE,
                ],
            ],
            'awaiting, sending, queued' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                ],
                'expectedIsStates' => [
                    EventDeliveryState::RUNNING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryState::AWAITING,
                    EventDeliveryState::COMPLETE,
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
                    EventDeliveryState::RUNNING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryState::AWAITING,
                    EventDeliveryState::COMPLETE,
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
                    EventDeliveryState::RUNNING,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryState::AWAITING,
                    EventDeliveryState::COMPLETE,
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
                    EventDeliveryState::COMPLETE,
                ],
                'expectedIsNotStates' => [
                    EventDeliveryState::AWAITING,
                    EventDeliveryState::RUNNING,
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
