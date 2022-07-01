<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use Symfony\Contracts\EventDispatcher\Event;

class ExecutionEvent extends Event implements EventInterface
{
    /**
     * @param non-empty-string $label
     */
    public function __construct(
        private readonly string $label,
        private readonly WorkerEventOutcome $outcome
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPayload(): array
    {
        return [];
    }

    public function getReferenceComponents(): array
    {
        return [];
    }

    public function getScope(): WorkerEventScope
    {
        return WorkerEventScope::EXECUTION;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return $this->outcome;
    }

    public function getRelatedReferenceSources(): array
    {
        return [];
    }
}
