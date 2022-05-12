<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\Document\Step;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractStepEvent extends Event implements EventInterface
{
    public function __construct(
        private readonly Step $step,
        private readonly string $path,
    ) {
    }

    public function getPayload(): array
    {
        return $this->step->getData();
    }

    public function getReferenceComponents(): array
    {
        return [
            $this->path,
            (string) $this->step->getName(),
        ];
    }
}
