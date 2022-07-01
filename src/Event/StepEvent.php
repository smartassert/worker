<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Model\Document\Step;
use Symfony\Contracts\EventDispatcher\Event;

class StepEvent extends Event implements EventInterface
{
    public function __construct(
        private readonly WorkerEventOutcome $outcome,
        private readonly Step $step,
        private readonly string $path,
        private readonly Test $test,
    ) {
    }

    public function getLabel(): string
    {
        return $this->step->getName();
    }

    public function getScope(): WorkerEventScope
    {
        return WorkerEventScope::STEP;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return $this->outcome;
    }

    public function getTest(): Test
    {
        return $this->test;
    }

    public function getPayload(): array
    {
        return [
            'source' => $this->path,
            'document' => $this->step->getData(),
            'name' => $this->step->getName(),
        ];
    }

    public function getReferenceComponents(): array
    {
        return [
            $this->path,
            $this->step->getName(),
        ];
    }

    public function getRelatedReferenceSources(): array
    {
        return [];
    }
}
