<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\Document\Test as TestDocument;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractTestEvent extends Event implements EventInterface
{
    public function __construct(
        private readonly TestDocument $document
    ) {
    }

    public function getPayload(): array
    {
        return [
            'source' => $this->document->getPath(),
            'document' => $this->document->getData(),
        ];
    }

    public function getReferenceComponents(): array
    {
        return [
            $this->document->getPath(),
        ];
    }
}
