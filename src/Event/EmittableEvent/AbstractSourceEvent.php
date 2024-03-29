<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Model\ResourceReferenceSource;

abstract class AbstractSourceEvent extends AbstractEvent implements EmittableEventInterface
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
            WorkerEventScope::SOURCE_COMPILATION,
            $outcome,
            array_merge(['source' => $source], $payload),
            array_merge([$source], $referenceComponents),
            $relatedReferenceSources,
        );
    }
}
