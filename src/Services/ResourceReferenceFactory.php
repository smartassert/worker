<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\ResourceReference;
use App\Model\ResourceReferenceCollection;
use App\Model\ResourceReferenceSource;

class ResourceReferenceFactory
{
    public function __construct(
        private readonly ReferenceFactory $referenceFactory,
    ) {
    }

    /**
     * @param ResourceReferenceSource[] $referenceSources
     */
    public function createCollection(array $referenceSources): ResourceReferenceCollection
    {
        $testReferences = [];
        foreach ($referenceSources as $referenceSource) {
            $testReferences[] = new ResourceReference(
                $referenceSource->label,
                $this->referenceFactory->create($referenceSource->components)
            );
        }

        return new ResourceReferenceCollection($testReferences);
    }
}
