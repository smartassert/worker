<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use webignition\BasilCompilerModels\Model\ErrorOutputInterface;

class SourceCompilationFailedEvent extends AbstractSourceEvent
{
    public function __construct(string $source, ErrorOutputInterface $errorOutput)
    {
        parent::__construct(
            $source,
            WorkerEventOutcome::FAILED,
            [
                'output' => $errorOutput->toArray(),
            ]
        );
    }
}
