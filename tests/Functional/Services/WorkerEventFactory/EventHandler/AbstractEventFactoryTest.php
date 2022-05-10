<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Tests\AbstractBaseFunctionalTest;
use Symfony\Contracts\EventDispatcher\Event;
use webignition\ObjectReflector\ObjectReflector;

abstract class AbstractEventFactoryTest extends AbstractBaseFunctionalTest
{
    private EventHandlerInterface $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->getFactory();
        if ($factory instanceof EventHandlerInterface) {
            $this->factory = $factory;
        }
    }

    /**
     * @return array<mixed>
     */
    abstract public function createDataProvider(): array;

    public function testCreateForEventUnsupportedEvent(): void
    {
        self::assertNull($this->factory->createForEvent(new Job(), new Event()));
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreateForEvent(Event $event, WorkerEvent $expectedWorkerEvent): void
    {
        $jobLabel = md5((string) rand());
        $job = Job::create($jobLabel, '', 600);

        $workerEvent = $this->factory->createForEvent($job, $event);

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

    abstract protected function getFactory(): ?EventHandlerInterface;
}
