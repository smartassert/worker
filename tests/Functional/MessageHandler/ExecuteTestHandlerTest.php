<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\TestState;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Message\ExecuteTestMessage;
use App\MessageHandler\ExecuteTestHandler;
use App\Repository\JobRepository;
use App\Services\ExecutionState;
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
        self::assertFalse($job->hasStarted());

        $executionState = self::getContainer()->get(ExecutionState::class);
        \assert($executionState instanceof ExecutionState);

        self::assertSame(ExecutionState::STATE_AWAITING, (string) $executionState);
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
                    function (TestStartedEvent $actualEvent) use (&$eventExpectationCount) {
                        self::assertInstanceOf(TestStartedEvent::class, $actualEvent);
                        ++$eventExpectationCount;

                        return true;
                    },
                ),
                new ExpectedDispatchedEvent(
                    function (TestPassedEvent $actualEvent) use ($test, &$eventExpectationCount) {
                        self::assertSame(
                            $test,
                            ObjectReflector::getProperty($actualEvent, 'test')
                        );
                        ++$eventExpectationCount;

                        return true;
                    },
                ),
            ]))
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, ExecuteTestHandler::class, 'eventDispatcher', $eventDispatcher);

        $executeTestMessage = new ExecuteTestMessage((int) $test->getId());
        ($this->handler)($executeTestMessage);

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');

        self::assertTrue($job->hasStarted());

        self::assertSame(ExecutionState::STATE_COMPLETE, (string) $executionState);
        self::assertSame(TestState::COMPLETE, $test->getState());
    }
}
