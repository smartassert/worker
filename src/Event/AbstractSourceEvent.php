<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractSourceEvent extends Event implements EventInterface
{
    /**
     * @param non-empty-string $source
     */
    public function __construct(private readonly string $source)
    {
    }

    /**
     * @return array{source: non-empty-string}
     */
    public function getPayload(): array
    {
        return [
            'source' => $this->source,
        ];
    }

    /**
     * @return array{0: non-empty-string}
     */
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
