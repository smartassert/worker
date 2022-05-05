<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Event\SourceCompilation\EventInterface;
use App\Event\StepEventInterface;
use App\Event\TestEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

class CallbackReferenceFactory
{
    /**
     * @return non-empty-string
     */
    public function createForEvent(Job $job, Event $event): string
    {
        $referenceComponents = [$job->getLabel()];

        if ($event instanceof EventInterface) {
            $referenceComponents[] = $event->getSource();
        }

        if ($event instanceof TestEventInterface || $event instanceof StepEventInterface) {
            $referenceComponents[] = $event->getTest()->getSource();
        }

        if ($event instanceof StepEventInterface) {
            $referenceComponents[] = $event->getStep()->getName();
        }

        return md5(implode('', $referenceComponents));
    }
}
