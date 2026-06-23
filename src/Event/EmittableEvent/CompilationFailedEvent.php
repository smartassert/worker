<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

class CompilationFailedEvent extends AbstractSourceEvent
{
    /**
     * @param array<mixed> $payloadOutput
     */
    public function __construct(string $source, array $payloadOutput)
    {
        parent::__construct(
            $source,
            EventTypeInterface::COMPILATION_FAILED,
            [
                'output' => $payloadOutput,
            ]
        );
    }
}
