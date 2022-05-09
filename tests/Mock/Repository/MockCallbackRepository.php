<?php

declare(strict_types=1);

namespace App\Tests\Mock\Repository;

use App\Entity\WorkerEvent;
use App\Repository\CallbackRepository;
use Mockery\MockInterface;

class MockCallbackRepository
{
    private CallbackRepository $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(CallbackRepository::class);
    }

    public function getMock(): CallbackRepository
    {
        return $this->mock;
    }

    public function withoutFindCall(): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldNotReceive('find')
        ;

        return $this;
    }

    public function withFindCall(int $callbackId, ?WorkerEvent $callback): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('find')
            ->with($callbackId)
            ->andReturn($callback)
        ;

        return $this;
    }
}
