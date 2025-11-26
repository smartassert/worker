<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Enum\ApplicationState;
use App\Event\JobCompletedEvent;
use App\Message\JobCompletedCheckMessage;
use App\Services\ApplicationProgress;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class JobCompleteEventDispatcher
{
    public function __construct(
        private readonly ApplicationProgress $applicationProgress,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageBusInterface $messageBus,
        private readonly int $dispatchDelay,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function dispatch(): void
    {
        $applicationState = $this->applicationProgress->get();

        if (ApplicationState::COMPLETE === $applicationState) {
            $this->eventDispatcher->dispatch(new JobCompletedEvent());

            return;
        }

        if (ApplicationState::TIMED_OUT !== $applicationState) {
            $this->messageBus->dispatch(
                new Envelope(new JobCompletedCheckMessage(), [new DelayStamp($this->dispatchDelay)])
            );
        }
    }
}
