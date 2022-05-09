<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Entity\WorkerEvent;
use App\Services\WorkerEventStateMutator;
use Mockery\MockInterface;

class MockCallbackStateMutator
{
    private WorkerEventStateMutator $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(WorkerEventStateMutator::class);
    }

    public function getMock(): WorkerEventStateMutator
    {
        return $this->mock;
    }

    public function withoutSetSendingCall(): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldNotReceive('setSending')
        ;

        return $this;
    }

    public function withoutSetCompleteCall(): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldNotReceive('setComplete')
        ;

        return $this;
    }

    public function withSetSendingCall(WorkerEvent $callback): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('setSending')
            ->with($callback)
        ;

        return $this;
    }
}
