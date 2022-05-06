<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Entity\Callback\CallbackEntity;
use App\Services\CallbackSender;
use Mockery\MockInterface;

class MockCallbackSender
{
    private CallbackSender $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(CallbackSender::class);
    }

    public function getMock(): CallbackSender
    {
        return $this->mock;
    }

    public function withSendCall(CallbackEntity $callback, ?\Exception $exception = null): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        if ($exception instanceof \Throwable) {
            $this->mock
                ->shouldReceive('send')
                ->withArgs(function (CallbackEntity $callbackArg) use ($callback) {
                    return $callbackArg->getId() === $callback->getId();
                })
                ->andThrow($exception)
            ;
        } else {
            $this->mock
                ->shouldReceive('send')
                ->withArgs(function (CallbackEntity $callbackArg) use ($callback) {
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
