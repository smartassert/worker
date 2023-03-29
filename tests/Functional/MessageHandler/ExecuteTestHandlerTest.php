<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\Test;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\EmittableEvent\TestEvent;
use App\Message\ExecuteTestMessage;
use App\MessageHandler\ExecuteTestHandler;
use App\Repository\JobRepository;
use App\Services\ExecutionProgress;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Mock\Services\MockTestExecutor;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use App\Tests\Services\EventRecorder;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\ObjectReflector\ObjectReflector;

class ExecuteTestHandlerTest extends AbstractBaseFunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private ExecuteTestHandler $handler;
    private EnvironmentFactory $environmentFactory;
    private EventRecorder $eventRecorder;

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

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;
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

        $testId = ObjectReflector::getProperty($test, 'id');
        self::assertIsInt($testId);

        $executeTestMessage = new ExecuteTestMessage($testId);
        ($this->handler)($executeTestMessage);

        self::assertSame(2, $this->eventRecorder->count());

        $firstTestEvent = $this->eventRecorder->get(0);
        self::assertInstanceOf(TestEvent::class, $firstTestEvent);
        self::assertSame(WorkerEventScope::TEST, $firstTestEvent->getScope());
        self::assertSame(WorkerEventOutcome::STARTED, $firstTestEvent->getOutcome());

        $secondTestEvent = $this->eventRecorder->get(1);
        self::assertInstanceOf(TestEvent::class, $secondTestEvent);
        self::assertSame(WorkerEventScope::TEST, $secondTestEvent->getScope());
        self::assertSame(WorkerEventOutcome::PASSED, $secondTestEvent->getOutcome());
        self::assertSame($test, $secondTestEvent->getTest());

        self::assertSame(ExecutionState::COMPLETE, $executionProgress->get());
        self::assertSame(TestState::COMPLETE, $test->getState());
    }
}
