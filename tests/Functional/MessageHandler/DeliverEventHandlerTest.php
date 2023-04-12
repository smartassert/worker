<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventState;
use App\Exception\EventDeliveryException;
use App\Message\DeliverEventMessage;
use App\MessageHandler\DeliverEventHandler;
use App\Repository\WorkerEventRepository;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DeliverEventHandlerTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    private DeliverEventHandler $handler;
    private WorkerEventRepository $workerEventRepository;
    private WorkerEvent $workerEvent;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(DeliverEventHandler::class);
        \assert($handler instanceof DeliverEventHandler);
        $this->handler = $handler;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $mockHandler = self::getContainer()->get('app.tests.services.guzzle.handler.queuing');
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

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
        $this->mockHandler->append(new Response(
            200,
            ['content-type' => 'application/json'],
            (string) json_encode($this->workerEvent)
        ));

        $message = new DeliverEventMessage((int) $this->workerEvent->getId());

        self::assertSame(WorkerEventState::QUEUED, $this->workerEvent->getState());

        ($this->handler)($message);

        $workerEvent = $this->workerEventRepository->find($this->workerEvent->getId());
        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertSame(WorkerEventState::COMPLETE, $workerEvent->getState());
    }

    public function testInvokeFailure(): void
    {
        $resultsClientResponse = new Response(400);
        $this->mockHandler->append($resultsClientResponse);

        $message = new DeliverEventMessage((int) $this->workerEvent->getId());

        self::assertSame(WorkerEventState::QUEUED, $this->workerEvent->getState());

        $expectedException = new EventDeliveryException(
            $this->workerEvent,
            new NonSuccessResponseException($resultsClientResponse)
        );

        try {
            ($this->handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Throwable $exception) {
            self::assertEquals($expectedException, $exception);
        }

        $workerEvent = $this->workerEventRepository->find($this->workerEvent->getId());
        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertSame(WorkerEventState::SENDING, $workerEvent->getState());
    }
}
