<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Job;
use App\Entity\Test;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Exception\JobNotFoundException;
use App\Message\ExecuteTestMessage;
use App\MessageHandler\ExecuteTestHandler;
use App\Repository\JobRepository;
use App\Repository\TestRepository;
use App\Services\ExecutionProgress;
use App\Services\TestExecutor;
use App\Services\TestStateMutator;
use App\Tests\Mock\Repository\MockJobRepository;
use App\Tests\Mock\Repository\MockTestRepository;
use App\Tests\Mock\Services\MockExecutionProgress;
use App\Tests\Mock\Services\MockTestExecutor;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ExecuteTestHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testInvokeNoJob(): void
    {
        $exception = new JobNotFoundException();

        $handler = new ExecuteTestHandler(
            (new MockJobRepository())->withGetCall($exception)->getMock(),
            \Mockery::mock(TestExecutor::class),
            \Mockery::mock(EventDispatcherInterface::class),
            \Mockery::mock(TestStateMutator::class),
            \Mockery::mock(TestRepository::class),
            \Mockery::mock(ExecutionProgress::class),
        );

        self::expectExceptionObject($exception);

        $handler(new ExecuteTestMessage(1));
    }

    /**
     * @dataProvider invokeNoExecutionDataProvider
     */
    public function testInvokeNoExecution(
        JobRepository $jobRepository,
        ExecutionProgress $executionProgress,
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
            $executionProgress,
        );

        $handler($message);
    }

    /**
     * @return array<mixed>
     */
    public function invokeNoExecutionDataProvider(): array
    {
        $testInWrongState = \Mockery::mock(Test::class);
        $testInWrongState
            ->shouldReceive('hasState')
            ->with(TestState::AWAITING)
            ->andReturn(false)
        ;

        $job = new Job(md5((string) rand()), 'https://example.com/events', 600, []);

        return [
            'execution state not awaiting, not running' => [
                'jobRepository' => (new MockJobRepository())
                    ->withGetCall($job)
                    ->getMock(),
                'executionProgress' => (new MockExecutionProgress())
                    ->withIsCall(true, ...ExecutionState::getFinishedStates())
                    ->getMock(),
                'message' => new ExecuteTestMessage(1),
                'testRepository' => (new MockTestRepository())
                    ->withoutFindCall()
                    ->getMock(),
            ],
            'no test' => [
                'jobRepository' => (new MockJobRepository())
                    ->withGetCall($job)
                    ->getMock(),
                'executionProgress' => (new MockExecutionProgress())
                    ->withIsCall(false, ...ExecutionState::getFinishedStates())
                    ->getMock(),
                'message' => new ExecuteTestMessage(1),
                'testRepository' => (new MockTestRepository())
                    ->withFindCall(1, null)
                    ->getMock(),
            ],
            'test in wrong state' => [
                'jobRepository' => (new MockJobRepository())
                    ->withGetCall($job)
                    ->getMock(),
                'executionProgress' => (new MockExecutionProgress())
                    ->withIsCall(false, ...ExecutionState::getFinishedStates())
                    ->getMock(),
                'message' => new ExecuteTestMessage(1),
                'testRepository' => (new MockTestRepository())
                    ->withFindCall(1, $testInWrongState)
                    ->getMock(),
            ],
        ];
    }
}
