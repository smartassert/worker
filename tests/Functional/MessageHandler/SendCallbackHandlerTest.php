<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Exception\NonSuccessfulHttpResponseException;
use App\Message\SendCallbackMessage;
use App\MessageHandler\SendCallbackHandler;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventSender;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Services\MockCallbackSender;
use App\Tests\Model\CallbackSetup;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\ObjectReflector\ObjectReflector;

class SendCallbackHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private SendCallbackHandler $handler;
    private WorkerEventRepository $workerEventRepository;
    private WorkerEvent $workerEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $sendCallbackHandler = self::getContainer()->get(SendCallbackHandler::class);
        \assert($sendCallbackHandler instanceof SendCallbackHandler);
        $this->handler = $sendCallbackHandler;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }

        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withCallbackSetups([
                (new CallbackSetup())
                    ->withState(WorkerEvent::STATE_QUEUED),
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
        $expectedSentWorkerEvent->setState(WorkerEvent::STATE_SENDING);

        $this->setWorkerEventSender((new MockCallbackSender())
            ->withSendCall($expectedSentWorkerEvent)
            ->getMock());

        $message = new SendCallbackMessage((int) $this->workerEvent->getId());

        self::assertSame(WorkerEvent::STATE_QUEUED, $this->workerEvent->getState());

        ($this->handler)($message);

        $workerEvent = $this->workerEventRepository->find($this->workerEvent->getId());
        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertSame(WorkerEvent::STATE_COMPLETE, $this->workerEvent->getState());
    }

    /**
     * @dataProvider invokeFailureDataProvider
     */
    public function testInvokeFailure(\Exception $workerEventSenderException, string $expectedWorkerEventState): void
    {
        $expectedSentWorkerEvent = clone $this->workerEvent;
        $expectedSentWorkerEvent->setState(WorkerEvent::STATE_SENDING);

        $this->setWorkerEventSender((new MockCallbackSender())
            ->withSendCall($expectedSentWorkerEvent, $workerEventSenderException)
            ->getMock());

        $message = new SendCallbackMessage((int) $this->workerEvent->getId());

        self::assertSame(WorkerEvent::STATE_QUEUED, $this->workerEvent->getState());

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
                    new WorkerEvent(),
                    new Response(400)
                ),
                'expectedWorkerEventState' => WorkerEvent::STATE_SENDING,
            ],
            'Guzzle ConnectException' => [
                'workerEventSenderException' => \Mockery::mock(ConnectException::class),
                'expectedWorkerEventState' => WorkerEvent::STATE_SENDING,
            ],
        ];
    }

    private function setWorkerEventSender(WorkerEventSender $workerEventSender): void
    {
        ObjectReflector::setProperty($this->handler, $this->handler::class, 'sender', $workerEventSender);
    }
}
