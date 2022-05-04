<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Job;
use App\Event\SourceCompilation\FailedEvent;
use App\Event\SourceCompilation\PassedEvent;
use App\Event\SourceCompilation\StartedEvent;

abstract class AbstractTestEventCallbackFactory extends AbstractCompilationEventCallbackFactory
{
    protected function createCallbackReference(Job $job, StartedEvent | PassedEvent | FailedEvent $event): string
    {
        return md5($job->getLabel() . $event->getSource());
    }
}
