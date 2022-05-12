<?php

declare(strict_types=1);

namespace App\Event\SourceCompilation;

use webignition\BasilCompilerModels\OutputInterface;

interface OutcomeSourceEventInterface extends SourceEventInterface
{
    public function getOutput(): OutputInterface;
}
