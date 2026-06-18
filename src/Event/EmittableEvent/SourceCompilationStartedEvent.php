<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Model\EventType\EventTypeInterface;

class SourceCompilationStartedEvent extends AbstractSourceEvent
{
    public function __construct(string $source)
    {
        parent::__construct($source, EventTypeInterface::SOURCE_COMPILATION_STARTED);
    }
}
