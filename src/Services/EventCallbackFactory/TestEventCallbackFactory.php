<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Job;
use App\Event\TestEventInterface;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class TestEventCallbackFactory extends AbstractEventCallbackFactory
{
    /**
     * @var array<class-string, CallbackInterface::TYPE_TEST_*>
     */
    private const EVENT_TO_CALLBACK_TYPE_MAP = [
        TestStartedEvent::class => CallbackInterface::TYPE_TEST_STARTED,
        TestPassedEvent::class => CallbackInterface::TYPE_TEST_PASSED,
        TestFailedEvent::class => CallbackInterface::TYPE_TEST_FAILED,
    ];

    public function handles(Event $event): bool
    {
        return $event instanceof TestEventInterface;
    }

    public function createForEvent(Job $job, Event $event): ?CallbackInterface
    {
        if ($event instanceof TestEventInterface) {
            $document = $event->getDocument();

            $documentData = $document->parse();
            $documentData = is_array($documentData) ? $documentData : [];

            return $this->create(
                $job,
                $event,
                self::EVENT_TO_CALLBACK_TYPE_MAP[$event::class],
                $documentData
            );
        }

        return null;
    }
}
