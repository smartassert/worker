<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Model\ResourceReferenceSource;

abstract class AbstractJobEvent extends AbstractEvent implements EmittableEventInterface
{
    /**
     * @param non-empty-string          $label
     * @param array<mixed>              $payload
     * @param string[]                  $referenceComponents
     * @param ResourceReferenceSource[] $relatedReferenceSources
     */
    public function __construct(
        string $label,
        WorkerEventOutcome $outcome,
        array $payload = [],
        array $referenceComponents = [],
        array $relatedReferenceSources = []
    ) {
        parent::__construct(
            $label,
            WorkerEventScope::JOB,
            $outcome,
            $payload,
            $referenceComponents,
            $relatedReferenceSources
        );
    }
}
