<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Test;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Message\ExecuteTestMessage;
use App\MessageHandler\ExecuteTestHandler;
use App\Repository\TestRepository;
use App\Services\ExecutionProgress;
use App\Services\TestStateMutator;
use App\Tests\Mock\Repository\MockTestRepository;
use App\Tests\Mock\Services\MockExecutionProgress;
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
        ExecutionProgress $executionProgress,
        ExecuteTestMessage $message,
        TestRepository $testRepository
    ): void {
        $testExecutor = (new MockTestExecutor())
            ->withoutExecuteCall()
            ->getMock()
        ;

        $handler = new ExecuteTestHandler(
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
            ->shouldReceive('getState')
            ->andReturn(TestState::COMPLETE)
        ;

        return [
            'execution state not awaiting, not running' => [
                'executionProgress' => (new MockExecutionProgress())
                    ->withIsCall(true, ExecutionState::getFinishedStates())
                    ->getMock(),
                'message' => new ExecuteTestMessage(1),
                'testRepository' => (new MockTestRepository())
                    ->withoutFindCall()
                    ->getMock(),
            ],
            'no test' => [
                'executionProgress' => (new MockExecutionProgress())
                    ->withIsCall(false, ExecutionState::getFinishedStates())
                    ->getMock(),
                'message' => new ExecuteTestMessage(1),
                'testRepository' => (new MockTestRepository())
                    ->withFindCall(1, null)
                    ->getMock(),
            ],
            'test in wrong state' => [
                'executionProgress' => (new MockExecutionProgress())
                    ->withIsCall(false, ExecutionState::getFinishedStates())
                    ->getMock(),
                'message' => new ExecuteTestMessage(1),
                'testRepository' => (new MockTestRepository())
                    ->withFindCall(1, $testInWrongState)
                    ->getMock(),
            ],
        ];
    }
}
