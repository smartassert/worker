<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test as TestEntity;
use App\Model\Document\Step as StepDocument;

interface StepEventInterface
{
    public function getTest(): TestEntity;

    public function getDocument(): StepDocument;
}
