<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEventReference;
use App\Model\ResourceReferenceSource;
use App\Model\WorkerEventReferenceCollection;
use App\Repository\WorkerEventReferenceRepository;

class WorkerEventReferenceFactory
{
    public function __construct(
        private readonly ReferenceFactory $referenceFactory,
        private readonly WorkerEventReferenceRepository $repository,
    ) {
    }

    /**
     * @param non-empty-string          $jobLabel
     * @param ResourceReferenceSource[] $referenceSources
     */
    public function createCollection(string $jobLabel, array $referenceSources): WorkerEventReferenceCollection
    {
        $testReferences = [];
        foreach ($referenceSources as $referenceSource) {
            $reference = $this->referenceFactory->create($jobLabel, $referenceSource->components);
            $resourceReference = $this->repository->findOneBy([
                'label' => $referenceSource->label,
                'reference' => $reference,
            ]);

            if (null === $resourceReference) {
                $resourceReference = $this->repository->add(
                    new WorkerEventReference($referenceSource->label, $reference)
                );
            }

            $testReferences[] = $resourceReference;
        }

        return new WorkerEventReferenceCollection($testReferences);
    }
}
