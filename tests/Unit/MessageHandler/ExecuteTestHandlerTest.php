<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Test;
use App\Message\ExecuteTestMessage;
use App\MessageHandler\ExecuteTestHandler;
use App\Repository\TestRepository;
use App\Services\EntityPersister;
use App\Services\EntityStore\JobStore;
use App\Services\ExecutionState;
use App\Services\TestDocumentFactory;
use App\Services\TestStateMutator;
use App\Tests\Mock\Entity\MockJob;
use App\Tests\Mock\Entity\MockTest;
use App\Tests\Mock\Repository\MockTestRepository;
use App\Tests\Mock\Services\MockExecutionState;
use App\Tests\Mock\Services\MockJobStore;
use App\Tests\Mock\Services\MockTestExecutor;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ExecuteTestHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @dataProvider invokeNoExecutionDataProvider
     */
    public function testInvokeNoExecution(
        JobStore $jobStore,
        ExecutionState $executionState,
        ExecuteTestMessage $message,
        TestRepository $testRepository
    ): void {
        $testExecutor = (new MockTestExecutor())
            ->withoutExecuteCall()
            ->getMock()
        ;

        $handler = new ExecuteTestHandler(
            $jobStore,
            \Mockery::mock(EntityPersister::class),
            $testExecutor,
            \Mockery::mock(EventDispatcherInterface::class),
            \Mockery::mock(TestStateMutator::class),
            $testRepository,
            $executionState,
            \Mockery::mock(TestDocumentFactory::class)
        );

        $handler($message);
    }

    /**
     * @return array<mixed>
     */
    public function invokeNoExecutionDataProvider(): array
    {
        $testInWrongState = (new MockTest())
            ->withHasStateCall(Test::STATE_AWAITING, false)
            ->getMock()
        ;

        return [
            'no job' => [
                'jobStore' => (new MockJobStore())
                    ->withGetCall(null)
                    ->getMock(),
                'executionState' => (new MockExecutionState())
                    ->getMock(),
                'message' => new ExecuteTestMessage(1),
                'testRepository' => (new MockTestRepository())
                    ->withoutFindCall()
                    ->getMock(),
            ],
            'execution state not awaiting, not running' => [
                'jobStore' => (new MockJobStore())
                    ->withGetCall((new MockJob())->getMock())
                    ->getMock(),
                'executionState' => (new MockExecutionState())
                    ->withIsCall(true, ...ExecutionState::FINISHED_STATES)
                    ->getMock(),
                'message' => new ExecuteTestMessage(1),
                'testRepository' => (new MockTestRepository())
                    ->withoutFindCall()
                    ->getMock(),
            ],
            'no test' => [
                'jobStore' => (new MockJobStore())
                    ->withGetCall((new MockJob())->getMock())
                    ->getMock(),
                'executionState' => (new MockExecutionState())
                    ->withIsCall(false, ...ExecutionState::FINISHED_STATES)
                    ->getMock(),
                'message' => new ExecuteTestMessage(1),
                'testRepository' => (new MockTestRepository())
                    ->withFindCall(1, null)
                    ->getMock(),
            ],
            'test in wrong state' => [
                'jobStore' => (new MockJobStore())
                    ->withGetCall((new MockJob())->getMock())
                    ->getMock(),
                'executionState' => (new MockExecutionState())
                    ->withIsCall(false, ...ExecutionState::FINISHED_STATES)
                    ->getMock(),
                'message' => new ExecuteTestMessage(1),
                'testRepository' => (new MockTestRepository())
                    ->withFindCall(1, $testInWrongState)
                    ->getMock(),
            ],
        ];
    }
}
