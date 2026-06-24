<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Event\EmittableEvent\EventTypeInterface as EventType;

class LifecycleEvent extends AbstractEvent implements EmittableEventInterface
{
    /**
     * @param non-empty-string       $label
     * @param EventType::LIFECYCLE_* $type
     */
    public function __construct(string $label, string $type)
    {
        parent::__construct($label, $type);
    }
}
