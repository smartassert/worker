<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractSourceEvent extends Event implements SourceEventInterface, EventInterface
{
    public function __construct(private string $source)
    {
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getPayload(): array
    {
        return [
            'source' => $this->getSource(),
        ];
    }

    public function getReferenceComponents(): array
    {
        return [
            $this->source,
        ];
    }
}
