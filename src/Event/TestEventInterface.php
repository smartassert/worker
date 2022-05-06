<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test as TestEntity;
use App\Model\Document\Test as TestDocument;

interface TestEventInterface
{
    public function getTest(): TestEntity;

    public function getDocument(): TestDocument;
}
