<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractSourceEvent extends Event implements EventInterface
{
    /**
     * @param non-empty-string $source
     */
    public function __construct(
        protected readonly string $source,
        private readonly WorkerEventOutcome $outcome,
    ) {
    }

    public function getScope(): WorkerEventScope
    {
        return WorkerEventScope::COMPILATION;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return $this->outcome;
    }

    public function getLabel(): string
    {
        return $this->source;
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
