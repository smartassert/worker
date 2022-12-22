<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventDispatcher;

use App\Enum\ApplicationState;
use App\Event\JobCompletedEvent;
use App\EventDispatcher\JobCompleteEventDispatcher;
use App\Message\JobCompletedCheckMessage;
use App\Messenger\MessageFactory;
use App\Services\ApplicationProgress;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
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
            \Mockery::mock(MessageFactory::class),
            \Mockery::mock(MessageBusInterface::class),
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

        $envelope = new Envelope(new JobCompletedCheckMessage());

        $messageFactory = \Mockery::mock(MessageFactory::class);
        $messageFactory
            ->shouldReceive('createDelayedEnvelope')
            ->andReturn($envelope)
        ;

        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus
            ->shouldReceive('dispatch')
            ->with($envelope)
            ->andReturn($envelope)
        ;

        $dispatcher = new JobCompleteEventDispatcher(
            $applicationProgress,
            $eventDispatcher,
            $messageFactory,
            $messageBus,
        );

        $dispatcher->dispatch();

        self::expectNotToPerformAssertions();
    }
}
