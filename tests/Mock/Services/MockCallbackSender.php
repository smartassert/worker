<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Entity\Callback\CallbackInterface;
use App\Model\SendCallbackResult;
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

    public function withSendCall(CallbackInterface $callback, ?SendCallbackResult $result): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('send')
            ->withArgs(function (CallbackInterface $callbackArg) use ($callback) {
                return $callbackArg->getId() === $callback->getId();
            })
            ->andReturn($result)
        ;

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
