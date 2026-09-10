<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Enum\ApplicationState;
use App\Enum\ExecutionState;
use App\Event\ExecutionCompletedEvent;
use App\Message\ExecutionCompletedCheckMessage;
use App\Services\ApplicationProgress;
use App\Services\ExecutionProgress;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class ExecutionCompletedEventDispatcher
{
    public function __construct(
        private readonly ApplicationProgress $applicationProgress,
        private readonly ExecutionProgress $executionProgress,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageBusInterface $messageBus,
        private readonly int $dispatchDelay,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function dispatch(): void
    {
        $executionState = $this->executionProgress->get();
        if (ExecutionState::COMPLETE === $executionState) {
            $this->eventDispatcher->dispatch(new ExecutionCompletedEvent());

            return;
        }

        $applicationState = $this->applicationProgress->get();
        if (ApplicationState::TIMED_OUT !== $applicationState) {
            $this->messageBus->dispatch(
                new Envelope(new ExecutionCompletedCheckMessage(), [new DelayStamp($this->dispatchDelay)])
            );
        }
    }
}
