<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Model\ResourceReferenceSource;

abstract class AbstractSourceEmittableEvent extends AbstractEmittableEvent implements EmittableEventInterface
{
    /**
     * @param non-empty-string          $source
     * @param array<mixed>              $payload
     * @param string[]                  $referenceComponents
     * @param ResourceReferenceSource[] $relatedReferenceSources
     */
    public function __construct(
        protected readonly string $source,
        readonly WorkerEventOutcome $outcome,
        array $payload = [],
        array $referenceComponents = [],
        array $relatedReferenceSources = []
    ) {
        parent::__construct(
            $source,
            WorkerEventScope::COMPILATION,
            $outcome,
            array_merge(['source' => $source], $payload),
            array_merge([$source], $referenceComponents),
            $relatedReferenceSources,
        );
    }
}
