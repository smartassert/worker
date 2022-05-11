<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Test as TestEntity;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobReadyEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilation\PassedEvent;
use App\Event\SourceCompilation\StartedEvent;
use App\Event\StepFailedEvent;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Message\DeliverEventMessage;
use App\MessageDispatcher\TimeoutCheckMessageDispatcher;
use App\Repository\WorkerEventRepository;
use App\Services\ApplicationWorkflowHandler;
use App\Services\CompilationWorkflowHandler;
use App\Services\ExecutionWorkflowHandler;
use App\Services\TestFactory;
use App\Services\TestStateMutator;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockSuiteManifest;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EventListenerRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

class DeliverEventMessageDispatcherTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private EventDispatcherInterface $eventDispatcher;
    private MessengerAsserter $messengerAsserter;
    private WorkerEventRepository $workerEventRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $eventListenerRemover = self::getContainer()->get(EventListenerRemover::class);
        \assert($eventListenerRemover instanceof EventListenerRemover);
        $eventListenerRemover->remove([
            TestStateMutator::class => [
                StepFailedEvent::class => ['setFailedFromStepFailedEvent'],
            ],
            TestFactory::class => [
                PassedEvent::class => ['createFromSourceCompileSuccessEvent'],
            ],
            ExecutionWorkflowHandler::class => [
                JobCompiledEvent::class => ['dispatchExecutionStartedEvent'],
                TestPassedEvent::class => [
                    'dispatchNextExecuteTestMessageFromTestPassedEvent',
                    'dispatchExecutionCompletedEvent',
                ],
            ],
            TimeoutCheckMessageDispatcher::class => [
                JobReadyEvent::class => ['dispatch'],
            ],
            ApplicationWorkflowHandler::class => [
                TestFailedEvent::class => ['dispatchJobFailedEvent'],
                TestPassedEvent::class => ['dispatchJobCompletedEvent'],
            ],
            CompilationWorkflowHandler::class => [
                JobReadyEvent::class => ['dispatchNextCompileSourceMessage'],
                PassedEvent::class => [
                    'dispatchNextCompileSourceMessage',
                    'dispatchCompilationCompletedEvent',
                ],
            ],
        ]);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(TestEntity::class);
            $entityRemover->removeForEntity(WorkerEvent::class);
        }
    }

    /**
     * @dataProvider subscribesToEventDataProvider
     *
     * @param array<mixed> $expectedWorkerEventPayload
     */
    public function testSubscribesToEvent(
        Event $event,
        WorkerEventType $expectedWorkerEventType,
        array $expectedWorkerEventPayload
    ): void {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->eventDispatcher->dispatch($event);

        $this->messengerAsserter->assertQueueCount(1);

        $envelope = $this->messengerAsserter->getEnvelopeAtPosition(0);
        $message = $envelope->getMessage();
        self::assertInstanceOf(DeliverEventMessage::class, $message);
        $workerEvent = $this->workerEventRepository->find($message->workerEventId);
        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertSame($expectedWorkerEventType, $workerEvent->getType());
        self::assertSame($expectedWorkerEventPayload, $workerEvent->getPayload());
    }

    /**
     * @return array<mixed>
     */
    public function subscribesToEventDataProvider(): array
    {
        $relativeTestSource = 'Test/test.yml';
        $testSource = '/app/source/' . $relativeTestSource;

        return [
            JobReadyEvent::class => [
                'event' => new JobReadyEvent(),
                'expectedWorkerEventType' => WorkerEventType::JOB_STARTED,
                'expectedWorkerEventPayload' => [],
            ],
            StartedEvent::class => [
                'event' => new StartedEvent($testSource),
                'expectedWorkerEventType' => WorkerEventType::COMPILATION_STARTED,
                'expectedWorkerEventPayload' => [
                    'source' => $testSource,
                ],
            ],
            PassedEvent::class => [
                'event' => new PassedEvent($testSource, (new MockSuiteManifest())->getMock()),
                'expectedWorkerEventType' => WorkerEventType::COMPILATION_PASSED,
                'expectedWorkerEventPayload' => [
                    'source' => $testSource,
                ],
            ],
            JobCompiledEvent::class => [
                'event' => new JobCompiledEvent(),
                'expectedWorkerEventType' => WorkerEventType::JOB_COMPILED,
                'expectedWorkerEventPayload' => [],
            ],
            ExecutionStartedEvent::class => [
                'event' => new ExecutionStartedEvent(),
                'expectedWorkerEventType' => WorkerEventType::EXECUTION_STARTED,
                'expectedWorkerEventPayload' => [],
            ],
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(10),
                'expectedWorkerEventType' => WorkerEventType::JOB_TIME_OUT,
                'expectedWorkerEventPayload' => [
                    'maximum_duration_in_seconds' => 10,
                ],
            ],
            JobCompletedEvent::class => [
                'event' => new JobCompletedEvent(),
                'expectedWorkerEventType' => WorkerEventType::JOB_COMPLETED,
                'expectedWorkerEventPayload' => [],
            ],
        ];
    }
}
