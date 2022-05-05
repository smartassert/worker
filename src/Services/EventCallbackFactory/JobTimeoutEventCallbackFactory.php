<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Job;
use App\Event\JobTimeoutEvent;
use Symfony\Contracts\EventDispatcher\Event;

class JobTimeoutEventCallbackFactory extends AbstractEventCallbackFactory
{
    public function handles(Event $event): bool
    {
        return $event instanceof JobTimeoutEvent;
    }

    public function createForEvent(Job $job, Event $event): ?CallbackInterface
    {
        if ($event instanceof JobTimeoutEvent) {
            return $this->create(
                CallbackInterface::TYPE_JOB_TIME_OUT,
                $this->createCallbackReference($job),
                [
                    'maximum_duration_in_seconds' => $event->getJobMaximumDuration(),
                ]
            );
        }

        return null;
    }

    /**
     * @return non-empty-string
     */
    private function createCallbackReference(Job $job): string
    {
        return md5((string) $job->getLabel());
    }
}
