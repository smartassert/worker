<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Model\EventType\EventTypeInterface;

class SourceCompilationFailedEvent extends AbstractSourceEvent
{
    /**
     * @param array<mixed> $payloadOutput
     */
    public function __construct(string $source, array $payloadOutput)
    {
        parent::__construct(
            $source,
            EventTypeInterface::SOURCE_COMPILATION_FAILED,
            [
                'output' => $payloadOutput,
            ]
        );
    }
}
