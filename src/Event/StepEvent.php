<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test;
use App\Enum\WorkerEventType;
use App\Model\Document\Step;
use Symfony\Contracts\EventDispatcher\Event;

class StepEvent extends Event implements EventInterface
{
    public function __construct(
        private readonly WorkerEventType $type,
        private readonly Step $step,
        private readonly string $path,
        private readonly Test $test,
    ) {
    }

    public function getType(): WorkerEventType
    {
        return $this->type;
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
            (string) $this->step->getName(),
        ];
    }

    public function getRelatedReferenceSources(): array
    {
        return [];
    }
}
