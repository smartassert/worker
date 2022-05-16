<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

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
     * @param ExecutionProgress::STATE_* $state
     */
    public function withGetCall(string $state): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('get')
            ->andReturn($state)
        ;

        return $this;
    }

    /**
     * @param array<ExecutionProgress::STATE_*> $states
     */
    public function withIsCall(bool $is, ...$states): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('is')
            ->with(...$states)
            ->andReturn($is)
        ;

        return $this;
    }
}
