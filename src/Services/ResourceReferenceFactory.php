<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
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
    public function createCollection(Job $job, array $values): ResourceReferenceCollection
    {
        $testReferences = [];
        foreach ($values as $value) {
            $testReferences[] = new ResourceReference(
                $value,
                $this->referenceFactory->create($job->getLabel(), [$value])
            );
        }

        return new ResourceReferenceCollection($testReferences);
    }
}
