<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test;
use webignition\YamlDocument\Document;

interface StepEventInterface
{
    public function getTest(): Test;

    public function getDocument(): Document;

    public function getStepName(): string;
}
