<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test;
use App\Model\Document\Step;
use Symfony\Contracts\EventDispatcher\Event;
use webignition\YamlDocument\Document;

abstract class AbstractStepEvent extends Event implements StepEventInterface
{
    public function __construct(
        private readonly Test $test,
        private readonly Document $document,
        private readonly Step $step,
    ) {
    }

    public function getTest(): Test
    {
        return $this->test;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getStep(): Step
    {
        return $this->step;
    }
}
