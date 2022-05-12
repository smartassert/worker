<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test;
use App\Model\Document\Step;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractStepEvent extends Event implements StepEventInterface, EventInterface
{
    public function __construct(
        private readonly Test $test,
        private readonly Step $step,
        private readonly string $path,
    ) {
    }

    public function getTest(): Test
    {
        return $this->test;
    }

    public function getDocument(): Step
    {
        return $this->step;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
