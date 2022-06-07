<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractSourceEvent extends Event implements EventInterface
{
    public function __construct(private string $source)
    {
    }

    public function getPayload(): array
    {
        return [
            'source' => $this->source,
        ];
    }

    public function getReferenceComponents(): array
    {
        return [
            $this->source,
        ];
    }

    public function getRelatedReferenceSources(): array
    {
        return [];
    }
}
