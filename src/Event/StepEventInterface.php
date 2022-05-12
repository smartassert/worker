<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test as TestEntity;

interface StepEventInterface
{
    public function getTest(): TestEntity;
}
