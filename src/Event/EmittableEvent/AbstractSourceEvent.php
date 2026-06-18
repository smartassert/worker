<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Model\EventType\EventTypeInterface;
use App\Model\ResourceReferenceSource;

abstract class AbstractSourceEvent extends AbstractEvent implements EmittableEventInterface
{
    /**
     * @param non-empty-string                         $source
     * @param EventTypeInterface::SOURCE_COMPILATION_* $type
     * @param array<mixed>                             $payload
     * @param string[]                                 $referenceComponents
     * @param ResourceReferenceSource[]                $relatedReferenceSources
     */
    public function __construct(
        protected readonly string $source,
        string $type,
        array $payload = [],
        array $referenceComponents = [],
        array $relatedReferenceSources = []
    ) {
        parent::__construct(
            $source,
            $type,
            array_merge(['source' => $source], $payload),
            array_merge([$source], $referenceComponents),
            $relatedReferenceSources,
        );
    }
}
