<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\ResourceReference;
use App\Model\ResourceReferenceCollection;
use App\Model\ResourceReferenceSource;

class ResourceReferenceFactory
{
    public function __construct(
        private readonly ReferenceFactory $referenceFactory,
    ) {
    }

    /**
     * @param non-empty-string          $jobLabel
     * @param ResourceReferenceSource[] $referenceSources
     */
    public function createCollection(string $jobLabel, array $referenceSources): ResourceReferenceCollection
    {
        $testReferences = [];
        foreach ($referenceSources as $referenceSource) {
            $testReferences[] = new ResourceReference(
                $referenceSource->label,
                $this->referenceFactory->create($jobLabel, $referenceSource->components)
            );
        }

        return new ResourceReferenceCollection($testReferences);
    }
}
