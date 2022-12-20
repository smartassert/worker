<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Enum\ApplicationState;
use App\Event\JobCompletedEvent;
use App\Message\JobCompletedCheckMessage;
use App\Messenger\MessageFactory;
use App\Services\ApplicationProgress;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class JobCompleteEventDispatcher
{
    public function __construct(
        private readonly ApplicationProgress $applicationProgress,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageFactory $messageFactory,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function dispatch(): void
    {
        if ($this->applicationProgress->is([ApplicationState::COMPLETE])) {
            $this->eventDispatcher->dispatch(new JobCompletedEvent());
        } else {
            $this->messageBus->dispatch(
                $this->messageFactory->createDelayedEnvelope(new JobCompletedCheckMessage())
            );
        }
    }
}
