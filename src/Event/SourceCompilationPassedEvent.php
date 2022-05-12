<?php

declare(strict_types=1);

namespace App\Event;

use webignition\BasilCompilerModels\SuiteManifest;

class SourceCompilationPassedEvent extends AbstractSourceEvent
{
    public function __construct(string $source, private SuiteManifest $suiteManifest)
    {
        parent::__construct($source);
    }

    public function getSuiteManifest(): SuiteManifest
    {
        return $this->suiteManifest;
    }
}
