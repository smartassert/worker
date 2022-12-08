<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Test as TestEntity;
use App\Enum\ApplicationState;
use App\Enum\ExecutionExceptionScope;
use App\Enum\JobEndedState;
use App\Enum\WorkerEventOutcome;
use App\Event\EventInterface;
use App\Event\JobEvent;
use App\Event\TestEvent;
use App\Message\JobCompletedCheckMessage;
use App\Model\Document\Exception;
use App\Model\Document\Test as TestDocument;
use App\Repository\JobRepository;
use App\Services\ApplicationWorkflowHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Mock\Services\MockApplicationProgress;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\ExpectedDispatchedEvent;
use App\Tests\Model\ExpectedDispatchedEventCollection;
use App\Tests\Model\JobSetup;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use webignition\ObjectReflector\ObjectReflector;

class ApplicationWorkflowHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private ApplicationWorkflowHandler $handler;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $applicationWorkflowHandler = self::getContainer()->get(ApplicationWorkflowHandler::class);
        \assert($applicationWorkflowHandler instanceof ApplicationWorkflowHandler);
        $this->handler = $applicationWorkflowHandler;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        if ($environmentFactory instanceof EnvironmentFactory) {
            $environmentFactory->create((new EnvironmentSetup())->withJobSetup(new JobSetup()));
        }
    }

    public function testSubscribesToTestPassedEventApplicationComplete(): void
    {
        $applicationProgress = (new MockApplicationProgress())
            ->withIsCall(true, [ApplicationState::COMPLETE])
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            ApplicationWorkflowHandler::class,
            'applicationProgress',
            $applicationProgress
        );

        $testEntity = new TestEntity('chrome', 'http://example.com', 'test.yml', '/', [], 0);
        $this->eventDispatcher->dispatch(new TestEvent(
            $testEntity,
            new TestDocument('test.yml', []),
            'test.yml',
            WorkerEventOutcome::PASSED
        ));

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);

        $messengerAsserter->assertQueueCount(2);
        $messengerAsserter->assertMessageAtPositionEquals(1, new JobCompletedCheckMessage());

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $job = $jobRepository->get();
        self::assertNull($job->endState);
    }

    /**
     * @dataProvider subscribesToTestFailureEventDataProvider
     */
    public function testSubscribesToTestFailedEvent(TestEvent $event): void
    {
        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(function (EventInterface $event) use (&$eventExpectationCount) {
                    self::assertInstanceOf(JobEvent::class, $event);
                    self::assertSame(WorkerEventOutcome::FAILED->value, $event->getOutcome()->value);
                    ++$eventExpectationCount;

                    return true;
                }),
            ]))
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            ApplicationWorkflowHandler::class,
            'eventDispatcher',
            $eventDispatcher
        );

        $this->eventDispatcher->dispatch($event);

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $job = $jobRepository->get();
        self::assertSame(JobEndedState::FAILED_TEST, $job->endState);
    }

    /**
     * @return array<mixed>
     */
    public function subscribesToTestFailureEventDataProvider(): array
    {
        $testEntity = new TestEntity('chrome', 'http://example.com', 'test.yml', '/', [], 0);

        return [
            'test/failed' => [
                'event' => new TestEvent(
                    $testEntity,
                    new TestDocument('test.yml', []),
                    'test.yml',
                    WorkerEventOutcome::FAILED
                ),
            ],
            'test/exception' => [
                'event' => new TestEvent(
                    $testEntity,
                    new Exception(ExecutionExceptionScope::TEST, []),
                    'test.yml',
                    WorkerEventOutcome::FAILED
                ),
            ],
        ];
    }
}
