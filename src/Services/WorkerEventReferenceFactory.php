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
     * @param non-empty-string $label
     * @param non-empty-string $reference
     */
    public function create(string $label, string $reference): WorkerEventReference
    {
        $workerEventReference = $this->repository->findOneBy([
            'label' => $label,
            'reference' => $reference,
        ]);

        if (null === $workerEventReference) {
            $workerEventReference = $this->repository->add(
                new WorkerEventReference($label, $reference)
            );
        }

        return $workerEventReference;
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
            $testReferences[] = $this->create($referenceSource->label, $reference);
        }

        return new WorkerEventReferenceCollection($testReferences);
    }
}
