<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Enum\ExecutionState;
use App\Services\ExecutionProgress;
use Mockery\MockInterface;

class MockExecutionProgress
{
    private ExecutionProgress $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(ExecutionProgress::class);
    }

    public function getMock(): ExecutionProgress
    {
        return $this->mock;
    }

    /**
     * @param ExecutionState[] $states
     */
    public function withIsCall(bool $is, array $states): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('is')
            ->with($states)
            ->andReturn($is)
        ;

        return $this;
    }
}
