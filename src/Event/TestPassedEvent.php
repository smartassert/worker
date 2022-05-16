<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test as TestEntity;
use App\Enum\WorkerEventType;
use App\Model\Document\Test as TestDocument;

class TestPassedEvent extends AbstractTestEvent
{
    public function __construct(
        TestDocument $document,
        private readonly TestEntity $test,
    ) {
        parent::__construct($document);
    }

    public function getTest(): TestEntity
    {
        return $this->test;
    }

    public function getType(): WorkerEventType
    {
        return WorkerEventType::TEST_PASSED;
    }
}
