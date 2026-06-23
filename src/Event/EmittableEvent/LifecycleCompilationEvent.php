<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Event\EmittableEvent\EventTypeInterface as EventType;

class LifecycleCompilationEvent extends AbstractEvent implements EmittableEventInterface
{
    /**
     * @param non-empty-string                                                                    $label
     * @param EventType::LIFECYCLE_COMPILATION_COMPLETED|EventType::LIFECYCLE_COMPILATION_STARTED $type
     */
    public function __construct(string $label, string $type)
    {
        parent::__construct($label, $type);
    }
}
