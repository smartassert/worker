<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Test as TestEntity;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Event\EmittableEvent\TestEvent;
use App\Message\JobCompletedCheckMessage;
use App\Model\Document\Test as TestDocument;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

class ApplicationWorkflowHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private EventDispatcherInterface $eventDispatcher;
    private TransportInterface $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        assert($entityRemover instanceof EntityRemover);
        $entityRemover->removeForEntity(Job::class);
        $entityRemover->removeForEntity(WorkerEvent::class);

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $environmentFactory->create((new EnvironmentSetup())->withJobSetup(new JobSetup()));

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof TransportInterface);
        $this->messengerTransport = $messengerTransport;
    }

    public function testSubscribesToTestPassedEventApplicationComplete(): void
    {
        $testEntity = new TestEntity('chrome', 'http://example.com', 'test.yml', '/', [], 0);
        $this->eventDispatcher->dispatch(new TestEvent(
            $testEntity,
            new TestDocument('test.yml', []),
            'test.yml',
            WorkerEventOutcome::PASSED
        ));

        $transportQueue = $this->messengerTransport->get();
        self::assertIsArray($transportQueue);
        self::assertCount(2, $transportQueue);

        $envelope = $transportQueue[1];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals(new JobCompletedCheckMessage(), $envelope->getMessage());
    }
}
