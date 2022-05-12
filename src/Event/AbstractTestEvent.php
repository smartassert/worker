<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test as TestEntity;
use App\Model\Document\Test as TestDocument;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractTestEvent extends Event implements TestEventInterface, EventInterface
{
    public function __construct(
        private readonly TestEntity $test,
        private readonly TestDocument $document
    ) {
    }

    public function getTest(): TestEntity
    {
        return $this->test;
    }

    public function getDocument(): TestDocument
    {
        return $this->document;
    }

    public function getPayload(): array
    {
        return $this->getDocument()->getData();
    }

    public function getReferenceComponents(): array
    {
        return [
            $this->document->getPath(),
        ];
    }
}
