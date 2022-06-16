<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use webignition\BasilCompilerModels\ErrorOutputInterface;

class SourceCompilationFailedEvent extends AbstractSourceEvent
{
    public function __construct(string $source, private ErrorOutputInterface $errorOutput)
    {
        parent::__construct($source);
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

    public function getScope(): WorkerEventScope
    {
        return WorkerEventScope::COMPILATION;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return WorkerEventOutcome::FAILED;
    }
}
