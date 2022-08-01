<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\Test;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\TestEvent;
use App\Message\ExecuteTestMessage;
use App\MessageHandler\ExecuteTestHandler;
use App\Repository\JobRepository;
use App\Services\ExecutionProgress;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Mock\Services\MockTestExecutor;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\ExpectedDispatchedEvent;
use App\Tests\Model\ExpectedDispatchedEventCollection;
use App\Tests\Model\JobSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\ObjectReflector\ObjectReflector;

class ExecuteTestHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private ExecuteTestHandler $handler;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $executeTestHandler = self::getContainer()->get(ExecuteTestHandler::class);
        \assert($executeTestHandler instanceof ExecuteTestHandler);
        $this->handler = $executeTestHandler;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Test::class);
        }
    }

    public function testInvokeExecuteSuccess(): void
    {
        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withTestSetups([
                new TestSetup(),
            ])
        ;

        $environment = $this->environmentFactory->create($environmentSetup);

        $tests = $environment->getTests();
        $test = $tests[0];

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $job = $jobRepository->get();
        self::assertInstanceOf(Job::class, $job);

        $executionProgress = self::getContainer()->get(ExecutionProgress::class);
        \assert($executionProgress instanceof ExecutionProgress);

        self::assertSame(ExecutionState::AWAITING, $executionProgress->get());
        self::assertSame(TestState::AWAITING, $test->getState());

        $testExecutor = (new MockTestExecutor())
            ->withExecuteCall($test)
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, ExecuteTestHandler::class, 'testExecutor', $testExecutor);

        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(
                    function (TestEvent $actualEvent) use (&$eventExpectationCount) {
                        self::assertSame(WorkerEventScope::TEST, $actualEvent->getScope());
                        self::assertSame(WorkerEventOutcome::STARTED, $actualEvent->getOutcome());
                        ++$eventExpectationCount;

                        return true;
                    },
                ),
                new ExpectedDispatchedEvent(
                    function (TestEvent $actualEvent) use ($test, &$eventExpectationCount) {
                        self::assertSame(WorkerEventScope::TEST, $actualEvent->getScope());
                        self::assertSame(WorkerEventOutcome::PASSED, $actualEvent->getOutcome());
                        self::assertSame($test, $actualEvent->getTest());
                        ++$eventExpectationCount;

                        return true;
                    },
                ),
            ]))
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, ExecuteTestHandler::class, 'eventDispatcher', $eventDispatcher);

        $testId = ObjectReflector::getProperty($test, 'id');
        self::assertIsInt($testId);

        $executeTestMessage = new ExecuteTestMessage($testId);
        ($this->handler)($executeTestMessage);

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');

        self::assertSame(ExecutionState::COMPLETE, $executionProgress->get());
        self::assertSame(TestState::COMPLETE, $test->getState());
    }
}
