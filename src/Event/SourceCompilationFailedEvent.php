<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;

class SourceCompilationFailedEvent extends AbstractSourceEmittableEvent
{
    /**
     * @param array<mixed> $payloadOutput
     */
    public function __construct(string $source, array $payloadOutput)
    {
        parent::__construct(
            $source,
            WorkerEventOutcome::FAILED,
            [
                'output' => $payloadOutput,
            ]
        );
    }
}
