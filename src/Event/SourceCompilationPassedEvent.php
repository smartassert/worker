<?php

declare(strict_types=1);

namespace App\Event;

use webignition\BasilCompilerModels\SuiteManifest;

class SourceCompilationPassedEvent extends AbstractSourceEvent implements OutcomeSourceEventInterface
{
    public function __construct(string $source, private SuiteManifest $suiteManifest)
    {
        parent::__construct($source);
    }

    public function getOutput(): SuiteManifest
    {
        return $this->suiteManifest;
    }
}
