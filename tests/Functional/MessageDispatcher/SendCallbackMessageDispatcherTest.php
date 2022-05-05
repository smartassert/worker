<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Test;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobReadyEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilation\FailedEvent;
use App\Event\SourceCompilation\PassedEvent;
use App\Event\SourceCompilation\StartedEvent;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Message\SendCallbackMessage;
use App\MessageDispatcher\TimeoutCheckMessageDispatcher;
use App\Model\Document\Step;
use App\Repository\CallbackRepository;
use App\Services\ApplicationWorkflowHandler;
use App\Services\ExecutionWorkflowHandler;
use App\Services\TestFactory;
use App\Services\TestStateMutator;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Entity\MockTest;
use App\Tests\Mock\MockSuiteManifest;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EventListenerRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;
use webignition\BasilCompilerModels\ErrorOutputInterface;
use webignition\YamlDocument\Document;

class SendCallbackMessageDispatcherTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private EventDispatcherInterface $eventDispatcher;
    private MessengerAsserter $messengerAsserter;
    private CallbackRepository $callbackRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $callbackRepository = self::getContainer()->get(CallbackRepository::class);
        \assert($callbackRepository instanceof CallbackRepository);
        $this->callbackRepository = $callbackRepository;

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
            ],
            TimeoutCheckMessageDispatcher::class => [
                JobReadyEvent::class => ['dispatch'],
            ],
            ApplicationWorkflowHandler::class => [
                TestFailedEvent::class => ['dispatchJobFailedEvent'],
                TestPassedEvent::class => ['dispatchJobCompletedEvent'],
            ],
        ]);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider subscribesToEventDataProvider
     *
     * @param array<mixed> $expectedCallbackPayload
     */
    public function testSubscribesToEvent(
        Event $event,
        string $expectedCallbackType,
        array $expectedCallbackPayload
    ): void {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->eventDispatcher->dispatch($event);

        $this->messengerAsserter->assertQueueCount(1);

        $envelope = $this->messengerAsserter->getEnvelopeAtPosition(0);
        $message = $envelope->getMessage();
        self::assertInstanceOf(SendCallbackMessage::class, $message);

        if ($message instanceof SendCallbackMessage) {
            $callback = $this->callbackRepository->find($message->getCallbackId());
            self::assertInstanceOf(CallbackInterface::class, $callback);

            if ($callback instanceof CallbackInterface) {
                self::assertSame($expectedCallbackType, $callback->getType());
                self::assertSame($expectedCallbackPayload, $callback->getPayload());
            }
        }
    }

    /**
     * @return array<mixed>
     */
    public function subscribesToEventDataProvider(): array
    {
        $sourceCompileFailureEventOutput = \Mockery::mock(ErrorOutputInterface::class);
        $sourceCompileFailureEventOutput
            ->shouldReceive('getData')
            ->andReturn([
                'compile-failure-key' => 'value',
            ])
        ;

        return [
            JobReadyEvent::class => [
                'event' => new JobReadyEvent(),
                'expectedCallbackType' => CallbackInterface::TYPE_JOB_STARTED,
                'expectedCallbackPayload' => [],
            ],
            StartedEvent::class => [
                'event' => new StartedEvent('/app/source/Test/test.yml'),
                'expectedCallbackType' => CallbackInterface::TYPE_COMPILATION_STARTED,
                'expectedCallbackPayload' => [
                    'source' => '/app/source/Test/test.yml',
                ],
            ],
            PassedEvent::class => [
                'event' => new PassedEvent(
                    '/app/source/Test/test.yml',
                    (new MockSuiteManifest())->getMock()
                ),
                'expectedCallbackType' => CallbackInterface::TYPE_COMPILATION_PASSED,
                'expectedCallbackPayload' => [
                    'source' => '/app/source/Test/test.yml',
                ],
            ],
            FailedEvent::class => [
                'event' => new FailedEvent(
                    '/app/source/Test/test.yml',
                    $sourceCompileFailureEventOutput
                ),
                'expectedCallbackType' => CallbackInterface::TYPE_COMPILATION_FAILED,
                'expectedCallbackPayload' => [
                    'source' => '/app/source/Test/test.yml',
                    'output' => [
                        'compile-failure-key' => 'value',
                    ],
                ],
            ],
            JobCompiledEvent::class => [
                'event' => new JobCompiledEvent(),
                'expectedCallbackType' => CallbackInterface::TYPE_JOB_COMPILED,
                'expectedCallbackPayload' => [],
            ],
            ExecutionStartedEvent::class => [
                'event' => new ExecutionStartedEvent(),
                'expectedCallbackType' => CallbackInterface::TYPE_EXECUTION_STARTED,
                'expectedCallbackPayload' => [],
            ],
            TestStartedEvent::class => [
                'event' => new TestStartedEvent(
                    (new MockTest())->getMock(),
                    new Document('document-key: value')
                ),
                'expectedCallbackType' => CallbackInterface::TYPE_TEST_STARTED,
                'expectedCallbackPayload' => [
                    'document-key' => 'value',
                ],
            ],
            StepPassedEvent::class => [
                'event' => new StepPassedEvent(
                    (new MockTest())->getMock(),
                    new Document('type: step' . "\n" . 'payload: { name: "passing step" }'),
                    new Step(
                        new Document('type: step' . "\n" . 'payload: { name: "passing step" }')
                    )
                ),
                'expectedCallbackType' => CallbackInterface::TYPE_STEP_PASSED,
                'expectedCallbackPayload' => [
                    'type' => 'step',
                    'payload' => [
                        'name' => 'passing step',
                    ],
                ],
            ],
            StepFailedEvent::class => [
                'event' => new StepFailedEvent(
                    (new MockTest())
                        ->withSetStateCall(Test::STATE_FAILED)
                        ->getMock(),
                    new Document('type: step' . "\n" . 'payload: { name: "failing step" }'),
                    new Step(
                        new Document('type: step' . "\n" . 'payload: { name: "failing step" }')
                    )
                ),
                'expectedCallbackType' => CallbackInterface::TYPE_STEP_FAILED,
                'expectedCallbackPayload' => [
                    'type' => 'step',
                    'payload' => [
                        'name' => 'failing step',
                    ],
                ],
            ],
            TestPassedEvent::class => [
                'event' => new TestPassedEvent(
                    (new MockTest())
                        ->withHasStateCall(Test::STATE_COMPLETE, false)
                        ->getMock(),
                    new Document('document-key: value')
                ),
                'expectedCallbackType' => CallbackInterface::TYPE_TEST_PASSED,
                'expectedCallbackPayload' => [
                    'document-key' => 'value',
                ],
            ],
            TestFailedEvent::class => [
                'event' => new TestFailedEvent(
                    (new MockTest())
                        ->withHasStateCall(Test::STATE_COMPLETE, false)
                        ->getMock(),
                    new Document('document-key: value')
                ),
                'expectedCallbackType' => CallbackInterface::TYPE_TEST_FAILED,
                'expectedCallbackPayload' => [
                    'document-key' => 'value',
                ],
            ],
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(10),
                'expectedCallbackType' => CallbackInterface::TYPE_JOB_TIME_OUT,
                'expectedCallbackPayload' => [
                    'maximum_duration_in_seconds' => 10,
                ],
            ],
            JobCompletedEvent::class => [
                'event' => new JobCompletedEvent(),
                'expectedCallbackType' => CallbackInterface::TYPE_JOB_COMPLETED,
                'expectedCallbackPayload' => [],
            ],
        ];
    }
}
