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
            $reference = $this->referenceFactory->create($jobLabel, $referenceSource->components);
            $resourceReference = $this->repository->findOneBy([
                'label' => $referenceSource->label,
                'reference' => $reference,
            ]);

            if (null === $resourceReference) {
                $resourceReference = $this->repository->add(new ResourceReference($referenceSource->label, $reference));
            }

            $testReferences[] = $resourceReference;
        }

        return new ResourceReferenceCollection($testReferences);
    }
}
