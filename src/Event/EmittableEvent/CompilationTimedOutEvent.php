<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

class CompilationTimedOutEvent extends AbstractSourceEvent
{
    public function __construct(string $source, int $timeoutInSeconds)
    {
        parent::__construct(
            $source,
            EventTypeInterface::COMPILATION_TIMED_OUT,
            [
                'timeout' => $timeoutInSeconds,
            ]
        );
    }
}
