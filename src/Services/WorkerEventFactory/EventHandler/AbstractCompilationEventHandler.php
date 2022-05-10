<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Event\SourceCompilation\EventInterface;

abstract class AbstractCompilationEventHandler extends AbstractEventHandler
{
    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    protected function createPayload(EventInterface $event, array $payload = []): array
    {
        return array_merge(
            [
                'source' => $event->getSource(),
            ],
            $payload
        );
    }
}
