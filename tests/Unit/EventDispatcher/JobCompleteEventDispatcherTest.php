<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventDispatcher;

use App\Enum\ApplicationState;
use App\Event\JobCompletedEvent;
use App\EventDispatcher\JobCompleteEventDispatcher;
use App\Message\JobCompletedCheckMessage;
use App\Services\ApplicationProgress;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\EventDispatcher\Event;

class JobCompleteEventDispatcherTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testDispatchesEventWhenJobIsComplete(): void
    {
        $applicationProgress = \Mockery::mock(ApplicationProgress::class);
        $applicationProgress
            ->shouldReceive('is')
            ->with([ApplicationState::COMPLETE])
            ->andReturn(true)
        ;

        $eventDispatcher = \Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher
            ->shouldReceive('dispatch')
            ->withArgs(function (Event $event) {
                self::assertInstanceOf(JobCompletedEvent::class, $event);

                return true;
            })
        ;

        $dispatcher = new JobCompleteEventDispatcher(
            $applicationProgress,
            $eventDispatcher,
            \Mockery::mock(MessageBusInterface::class),
            100
        );

        $dispatcher->dispatch();
    }

    public function testDispatchesMessageWhenJobIsNotCompleteAndNotTimedOut(): void
    {
        $applicationProgress = \Mockery::mock(ApplicationProgress::class);
        $applicationProgress
            ->shouldReceive('is')
            ->with([ApplicationState::COMPLETE])
            ->andReturn(false)
        ;

        $applicationProgress
            ->shouldReceive('is')
            ->with([ApplicationState::TIMED_OUT])
            ->andReturn(false)
        ;

        $eventDispatcher = \Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher
            ->shouldReceive('dispatch')
            ->withArgs(function (Event $event) {
                self::assertInstanceOf(JobCompletedEvent::class, $event);

                return true;
            })
        ;

        $dispatchDelay = rand(0, 1000);
        $expectedDelayStamp = new DelayStamp($dispatchDelay);

        $expectedEnvelope = new Envelope(new JobCompletedCheckMessage(), [$expectedDelayStamp]);

        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus
            ->shouldReceive('dispatch')
            ->withArgs(function (Envelope $envelope) use ($expectedEnvelope) {
                self::assertEquals($expectedEnvelope, $envelope);

                return true;
            })
            ->andReturn($expectedEnvelope)
        ;

        $dispatcher = new JobCompleteEventDispatcher(
            $applicationProgress,
            $eventDispatcher,
            $messageBus,
            $dispatchDelay
        );

        $dispatcher->dispatch();
    }
}
