<?php

declare(strict_types=1);

namespace App\Event;

use webignition\BasilCompilerModels\ErrorOutputInterface;

class SourceCompilationFailedEvent extends AbstractSourceEvent implements OutcomeSourceEventInterface
{
    public function __construct(string $source, private ErrorOutputInterface $errorOutput)
    {
        parent::__construct($source);
    }

    public function getOutput(): ErrorOutputInterface
    {
        return $this->errorOutput;
    }
}
