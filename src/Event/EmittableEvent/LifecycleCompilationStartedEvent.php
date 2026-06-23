<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

class LifecycleCompilationStartedEvent extends AbstractEvent implements EmittableEventInterface
{
    public function __construct(string $label)
    {
        parent::__construct($label, EventTypeInterface::LIFECYCLE_COMPILATION_STARTED);
    }
}
