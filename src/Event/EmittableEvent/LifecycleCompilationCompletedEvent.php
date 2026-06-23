<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

class LifecycleCompilationCompletedEvent extends AbstractEvent implements EmittableEventInterface
{
    public function __construct(string $label)
    {
        parent::__construct($label, EventTypeInterface::JOB_COMPILATION_COMPLETED);
    }
}
