<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;
use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Tests\AbstractBaseFunctionalTest;
use webignition\ObjectReflector\ObjectReflector;

abstract class AbstractEventHandlerTest extends AbstractBaseFunctionalTest
{
    private EventHandlerInterface $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->getHandler();
        if ($factory instanceof EventHandlerInterface) {
            $this->handler = $factory;
        }
    }

    /**
     * @return array<mixed>
     */
    abstract public function createDataProvider(): array;

//    public function testCreateForEventUnsupportedEvent(): void
//    {
//        $event = \Mockery::mock(EventInterface::class);
//        $event
//            ->shouldReceive('getPayload')
//            ->andReturn([]);
//
//        self::assertNull($this->handler->createForEvent(new Job(), $event));
//    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreateForEvent(EventInterface $event, WorkerEvent $expectedWorkerEvent): void
    {
        $jobLabel = md5((string) rand());
        $job = Job::create($jobLabel, '', 600);

        $workerEvent = $this->handler->createForEvent($job, $event);

        $expectedReferenceSource = str_replace('{{ job_label }}', $jobLabel, $expectedWorkerEvent->getReference());
        ObjectReflector::setProperty(
            $expectedWorkerEvent,
            WorkerEvent::class,
            'reference',
            md5($expectedReferenceSource)
        );

        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertNotNull($workerEvent->getId());
        self::assertSame($expectedWorkerEvent->getType(), $workerEvent->getType());
        self::assertSame($expectedWorkerEvent->getReference(), $workerEvent->getReference());
        self::assertSame($expectedWorkerEvent->getPayload(), $workerEvent->getPayload());
    }

    abstract protected function getHandler(): ?EventHandlerInterface;
}
