<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Enum\EventDeliveryState;
use App\Enum\WorkerEventState;
use App\Services\EventDeliveryProgress;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventDeliveryProgressTest extends WebTestCase
{
    private EventDeliveryProgress $eventDeliveryProgress;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDeliveryProgress = self::getContainer()->get(EventDeliveryProgress::class);
        \assert($eventDeliveryProgress instanceof EventDeliveryProgress);
        $this->eventDeliveryProgress = $eventDeliveryProgress;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAll();
        }
    }

    #[DataProvider('getDataProvider')]
    public function testGet(EnvironmentSetup $setup, EventDeliveryState $expected): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expected, $this->eventDeliveryProgress->get());
    }

    /**
     * @return array<mixed>
     */
    public static function getDataProvider(): array
    {
        return [
            'no events' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup()),
                'expected' => EventDeliveryState::AWAITING,
            ],
            'awaiting, sending, queued' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withWorkerEventSetups([
                        new WorkerEventSetup()->withState(WorkerEventState::AWAITING),
                        new WorkerEventSetup()->withState(WorkerEventState::QUEUED),
                        new WorkerEventSetup()->withState(WorkerEventState::SENDING),
                    ]),
                'expected' => EventDeliveryState::RUNNING,
            ],
            'awaiting, sending, queued, complete' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withWorkerEventSetups([
                        new WorkerEventSetup()->withState(WorkerEventState::AWAITING),
                        new WorkerEventSetup()->withState(WorkerEventState::QUEUED),
                        new WorkerEventSetup()->withState(WorkerEventState::SENDING),
                        new WorkerEventSetup()->withState(WorkerEventState::COMPLETE),
                    ]),
                'expected' => EventDeliveryState::RUNNING,
            ],
            'awaiting, sending, queued, failed' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withWorkerEventSetups([
                        new WorkerEventSetup()->withState(WorkerEventState::AWAITING),
                        new WorkerEventSetup()->withState(WorkerEventState::QUEUED),
                        new WorkerEventSetup()->withState(WorkerEventState::SENDING),
                        new WorkerEventSetup()->withState(WorkerEventState::FAILED),
                    ]),
                'expected' => EventDeliveryState::RUNNING,
            ],
            'two complete, three failed' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withWorkerEventSetups([
                        new WorkerEventSetup()->withState(WorkerEventState::COMPLETE),
                        new WorkerEventSetup()->withState(WorkerEventState::FAILED),
                        new WorkerEventSetup()->withState(WorkerEventState::FAILED),
                        new WorkerEventSetup()->withState(WorkerEventState::FAILED),
                        new WorkerEventSetup()
                            ->withType('job/ended')
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expected' => EventDeliveryState::COMPLETE,
            ],
        ];
    }
}
