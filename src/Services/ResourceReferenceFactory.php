<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\ResourceReference;
use App\Model\ResourceReferenceCollection;

class ResourceReferenceFactory
{
    public function __construct(
        private readonly ReferenceFactory $referenceFactory,
    ) {
    }

    /**
     * @param string[] $values
     */
    public function createCollection(array $values): ResourceReferenceCollection
    {
        $testReferences = [];
        foreach ($values as $value) {
            $testReferences[] = new ResourceReference(
                $value,
                $this->referenceFactory->create([$value])
            );
        }

        return new ResourceReferenceCollection($testReferences);
    }
}
