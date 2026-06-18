<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventType;

class ExecutionEvent extends AbstractEvent implements EmittableEventInterface
{
    /**
     * @param non-empty-string $label
     */
    public function __construct(string $label, WorkerEventType $type)
    {
        parent::__construct($label, $type, [], [], []);
    }
}
