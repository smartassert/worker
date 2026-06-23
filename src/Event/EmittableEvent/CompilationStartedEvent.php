<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

class CompilationStartedEvent extends AbstractSourceEvent
{
    public function __construct(string $source)
    {
        parent::__construct($source, EventTypeInterface::COMPILATION_STARTED);
    }
}
