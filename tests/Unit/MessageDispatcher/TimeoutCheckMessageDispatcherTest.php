<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageDispatcher;

use App\Message\TimeoutCheckMessage;
use App\MessageDispatcher\TimeoutCheckMessageDispatcher;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class TimeoutCheckMessageDispatcherTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testDispatchCreatesDelayedEnvelope(): void
    {
        $dispatchDelay = rand(0, 1000);
        $expectedDelayStamp = new DelayStamp($dispatchDelay);

        $expectedEnvelope = new Envelope(new TimeoutCheckMessage(), [$expectedDelayStamp]);

        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus
            ->shouldReceive('dispatch')
            ->withArgs(function (Envelope $envelope) use ($expectedEnvelope) {
                self::assertEquals($expectedEnvelope, $envelope);

                return true;
            })
            ->andReturn($expectedEnvelope)
        ;

        $dispatcher = new TimeoutCheckMessageDispatcher(
            $messageBus,
            $dispatchDelay
        );

        $dispatcher->dispatch();
    }
}
