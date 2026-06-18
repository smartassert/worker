<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Event\EmittableEvent\EventTypeInterface as EventType;
use App\Model\ResourceReferenceSource;

abstract class AbstractJobEvent extends AbstractEvent implements EmittableEventInterface
{
    /**
     * @param non-empty-string                                                     $label
     * @param EventType::JOB_ENDED|EventType::JOB_STARTED|EventType::JOB_TIMED_OUT $type
     * @param array<mixed>                                                         $payload
     * @param string[]                                                             $referenceComponents
     * @param ResourceReferenceSource[]                                            $relatedReferenceSources
     */
    public function __construct(
        string $label,
        string $type,
        array $payload = [],
        array $referenceComponents = [],
        array $relatedReferenceSources = []
    ) {
        parent::__construct(
            $label,
            $type,
            $payload,
            $referenceComponents,
            $relatedReferenceSources
        );
    }
}
