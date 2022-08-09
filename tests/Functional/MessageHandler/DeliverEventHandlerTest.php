<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Exception\NonSuccessfulHttpResponseException;
use App\Message\DeliverEventMessage;
use App\MessageHandler\DeliverEventHandler;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventSender;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Services\MockWorkerEventSender;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\ObjectReflector\ObjectReflector;

class DeliverEventHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private DeliverEventHandler $handler;
    private WorkerEventRepository $workerEventRepository;
    private WorkerEvent $workerEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(DeliverEventHandler::class);
        \assert($handler instanceof DeliverEventHandler);
        $this->handler = $handler;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }

        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withWorkerEventSetups([
                (new WorkerEventSetup())
                    ->withState(WorkerEventState::QUEUED),
            ])
        ;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $environment = $environmentFactory->create($environmentSetup);

        $workerEvents = $environment->getWorkerEvents();
        self::assertCount(1, $workerEvents);

        $workerEvent = $workerEvents[0];
        self::assertInstanceOf(WorkerEvent::class, $workerEvent);

        $this->workerEvent = $workerEvent;
    }

    public function testInvokeSuccess(): void
    {
        $expectedSentWorkerEvent = clone $this->workerEvent;
        $expectedSentWorkerEvent->setState(WorkerEventState::SENDING);

        $this->setWorkerEventSender((new MockWorkerEventSender())
            ->withSendCall($expectedSentWorkerEvent)
            ->getMock());

        $message = new DeliverEventMessage((int) $this->workerEvent->getId());

        self::assertSame(WorkerEventState::QUEUED, $this->workerEvent->getState());

        ($this->handler)($message);

        $workerEvent = $this->workerEventRepository->find($this->workerEvent->getId());
        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertSame(WorkerEventState::COMPLETE, $this->workerEvent->getState());
    }

    /**
     * @dataProvider invokeFailureDataProvider
     */
    public function testInvokeFailure(
        \Exception $workerEventSenderException,
        WorkerEventState $expectedWorkerEventState
    ): void {
        $expectedSentWorkerEvent = clone $this->workerEvent;
        $expectedSentWorkerEvent->setState(WorkerEventState::SENDING);

        $this->setWorkerEventSender((new MockWorkerEventSender())
            ->withSendCall($expectedSentWorkerEvent, $workerEventSenderException)
            ->getMock());

        $message = new DeliverEventMessage((int) $this->workerEvent->getId());

        self::assertSame(WorkerEventState::QUEUED, $this->workerEvent->getState());

        try {
            ($this->handler)($message);
            $this->fail($workerEventSenderException::class . ' not thrown');
        } catch (\Throwable $exception) {
            self::assertSame($workerEventSenderException, $exception);
        }

        $workerEvent = $this->workerEventRepository->find($this->workerEvent->getId());
        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertSame($expectedWorkerEventState, $this->workerEvent->getState());
    }

    /**
     * @return array<mixed>
     */
    public function invokeFailureDataProvider(): array
    {
        return [
            'HTTP 400' => [
                'workerEventSenderException' => new NonSuccessfulHttpResponseException(
                    new WorkerEvent(
                        WorkerEventScope::JOB,
                        WorkerEventOutcome::STARTED,
                        new WorkerEventReference('non-empty label', md5('reference source')),
                        []
                    ),
                    new Response(400)
                ),
                'expectedWorkerEventState' => WorkerEventState::SENDING,
            ],
            'Guzzle ConnectException' => [
                'workerEventSenderException' => \Mockery::mock(ConnectException::class),
                'expectedWorkerEventState' => WorkerEventState::SENDING,
            ],
        ];
    }

    private function setWorkerEventSender(WorkerEventSender $workerEventSender): void
    {
        ObjectReflector::setProperty($this->handler, $this->handler::class, 'sender', $workerEventSender);
    }
}
