<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Entity\WorkerEvent;
use App\Services\WorkerEventSender;
use Mockery\MockInterface;

class MockCallbackSender
{
    private WorkerEventSender $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(WorkerEventSender::class);
    }

    public function getMock(): WorkerEventSender
    {
        return $this->mock;
    }

    public function withSendCall(WorkerEvent $callback, ?\Exception $exception = null): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        if ($exception instanceof \Throwable) {
            $this->mock
                ->shouldReceive('send')
                ->withArgs(function (WorkerEvent $callbackArg) use ($callback) {
                    return $callbackArg->getId() === $callback->getId();
                })
                ->andThrow($exception)
            ;
        } else {
            $this->mock
                ->shouldReceive('send')
                ->withArgs(function (WorkerEvent $callbackArg) use ($callback) {
                    return $callbackArg->getId() === $callback->getId();
                })
            ;
        }

        return $this;
    }

    public function withoutSendCall(): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldNotReceive('send')
        ;

        return $this;
    }
}
