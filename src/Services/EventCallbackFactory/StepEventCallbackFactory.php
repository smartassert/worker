<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Job;
use App\Event\StepEventInterface;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Model\Document\Step;
use Symfony\Contracts\EventDispatcher\Event;

class StepEventCallbackFactory extends AbstractEventCallbackFactory
{
    /**
     * @var array<class-string, CallbackInterface::TYPE_STEP_*>
     */
    private const EVENT_TO_CALLBACK_TYPE_MAP = [
        StepPassedEvent::class => CallbackInterface::TYPE_STEP_PASSED,
        StepFailedEvent::class => CallbackInterface::TYPE_STEP_FAILED,
    ];

    public function handles(Event $event): bool
    {
        return $event instanceof StepEventInterface;
    }

    public function createForEvent(Job $job, Event $event): ?CallbackInterface
    {
        if ($event instanceof StepEventInterface) {
            $document = $event->getDocument();

            $documentData = $document->parse();
            $documentData = is_array($documentData) ? $documentData : [];

            return $this->create(
                self::EVENT_TO_CALLBACK_TYPE_MAP[$event::class],
                $this->createCallbackReference($job, $event),
                $documentData
            );
        }

        return null;
    }

    private function createCallbackReference(Job $job, StepEventInterface $event): string
    {
        return md5($job->getLabel() . $event->getTest()->getSource() . $event->getStep()->getName());
    }
}
