<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Entity\Test as TestEntity;

interface HasTestInterface
{
    public function getTest(): TestEntity;
}
