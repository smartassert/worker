<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventOutcome;

class SourceCompilationTimedOutEvent extends AbstractSourceEvent
{
    public function __construct(string $source, int $timeoutInSeconds)
    {
        parent::__construct(
            $source,
            WorkerEventOutcome::TIME_OUT,
            [
                'timeout' => $timeoutInSeconds,
            ]
        );
    }
}
