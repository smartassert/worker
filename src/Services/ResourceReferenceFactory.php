<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\ResourceReference;
use App\Model\ResourceReferenceCollection;
use App\Model\ResourceReferenceSource;
use App\Repository\ResourceReferenceRepository;

class ResourceReferenceFactory
{
    public function __construct(
        private readonly ReferenceFactory $referenceFactory,
        private readonly ResourceReferenceRepository $repository,
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
            $resourceReference = new ResourceReference(
                $referenceSource->label,
                $this->referenceFactory->create($jobLabel, $referenceSource->components)
            );

            $existingResourceReference = $this->repository->findOneBy([
                'label' => $resourceReference->getLabel(),
                'reference' => $resourceReference->getReference(),
            ]);

            if ($existingResourceReference instanceof ResourceReference) {
                $resourceReference = $existingResourceReference;
            } else {
                $this->repository->add($resourceReference);
            }

            $testReferences[] = $resourceReference;
        }

        return new ResourceReferenceCollection($testReferences);
    }
}
