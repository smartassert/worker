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
    public function testGet(array $states, EventDeliveryState $expected): void
    {
        foreach ($states as $workerEventState) {
            $this->createWorkerEventEntity($workerEventState);
        }

        self::assertSame($expected, $this->eventDeliveryProgress->get());
    }

    /**
     * @return array<mixed>
     */
    public static function getDataProvider(): array
    {
        return [
            'no events' => [
                'states' => [],
                'expected' => EventDeliveryState::AWAITING,
            ],
            'awaiting, sending, queued' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                ],
                'expected' => EventDeliveryState::RUNNING,
            ],
            'awaiting, sending, queued, complete' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                    WorkerEventState::COMPLETE,
                ],
                'expected' => EventDeliveryState::RUNNING,
            ],
            'awaiting, sending, queued, failed' => [
                'states' => [
                    WorkerEventState::AWAITING,
                    WorkerEventState::QUEUED,
                    WorkerEventState::SENDING,
                    WorkerEventState::FAILED,
                ],
                'expected' => EventDeliveryState::RUNNING,
            ],
            'two complete, three failed' => [
                'states' => [
                    WorkerEventState::COMPLETE,
                    WorkerEventState::COMPLETE,
                    WorkerEventState::FAILED,
                    WorkerEventState::FAILED,
                    WorkerEventState::FAILED,
                ],
                'expected' => EventDeliveryState::COMPLETE,
            ],
        ];
    }

    private function createWorkerEventEntity(WorkerEventState $state): void
    {
        $this->testWorkerEventFactory->create(
            new WorkerEventSetup()->withState($state)
        );
    }
}
