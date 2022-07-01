<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use webignition\BasilCompilerModels\Model\ErrorOutputInterface;

class SourceCompilationFailedEvent extends AbstractSourceEvent
{
    public function __construct(string $source, private ErrorOutputInterface $errorOutput)
    {
        parent::__construct($source, WorkerEventOutcome::FAILED);
    }

    public function getPayload(): array
    {
        return array_merge(
            parent::getPayload(),
            [
                'output' => $this->errorOutput->toArray(),
            ]
        );
    }
}
