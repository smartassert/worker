<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Job;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\JobReadyEvent;
use Symfony\Contracts\EventDispatcher\Event;

class NoPayloadEventCallbackFactory extends AbstractEventCallbackFactory
{
    /**
     * @var array<class-string, CallbackInterface::TYPE_*>
     */
    private const EVENT_TO_CALLBACK_TYPE_MAP = [
        JobReadyEvent::class => CallbackInterface::TYPE_JOB_STARTED,
        JobCompiledEvent::class => CallbackInterface::TYPE_JOB_COMPILED,
        ExecutionStartedEvent::class => CallbackInterface::TYPE_EXECUTION_STARTED,
        ExecutionCompletedEvent::class => CallbackInterface::TYPE_EXECUTION_COMPLETED,
        JobCompletedEvent::class => CallbackInterface::TYPE_JOB_COMPLETED,
        JobFailedEvent::class => CallbackInterface::TYPE_JOB_FAILED,
    ];

    public function handles(Event $event): bool
    {
        return
            $event instanceof JobReadyEvent
            || $event instanceof JobCompiledEvent
            || $event instanceof ExecutionStartedEvent
            || $event instanceof ExecutionCompletedEvent
            || $event instanceof JobCompletedEvent
            || $event instanceof JobFailedEvent
            ;
    }

    public function createForEvent(Job $job, Event $event): ?CallbackInterface
    {
        if ($this->handles($event)) {
            return $this->create(
                self::EVENT_TO_CALLBACK_TYPE_MAP[$event::class],
                $this->callbackReferenceFactory->createForEvent($job, $event),
                []
            );
        }

        return null;
    }
}
