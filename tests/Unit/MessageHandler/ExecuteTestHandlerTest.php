<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\TestState;
use App\Message\ExecuteTestMessage;
use App\MessageHandler\ExecuteTestHandler;
use App\Repository\JobRepository;
use App\Repository\TestRepository;
use App\Services\ExecutionState;
use App\Services\TestDocumentFactory;
use App\Services\TestStateMutator;
use App\Tests\Mock\Repository\MockJobRepository;
use App\Tests\Mock\Repository\MockTestRepository;
use App\Tests\Mock\Services\MockExecutionState;
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
        JobRepository $jobRepository,
        ExecutionState $executionState,
        ExecuteTestMessage $message,
        TestRepository $testRepository
    ): void {
        $testExecutor = (new MockTestExecutor())
            ->withoutExecuteCall()
            ->getMock()
        ;

        $handler = new ExecuteTestHandler(
            $jobRepository,
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
        $testInWrongState = (new Test())->setState(TestState::CANCELLED);

        return [
            'no job' => [
                'jobRepository' => (new MockJobRepository())
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
                'jobRepository' => (new MockJobRepository())
                    ->withGetCall(new Job())
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
                'jobRepository' => (new MockJobRepository())
                    ->withGetCall(new Job())
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
                'jobRepository' => (new MockJobRepository())
                    ->withGetCall(new Job())
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
