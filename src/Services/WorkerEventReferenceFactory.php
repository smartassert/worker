<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEventReference;
use App\Model\ResourceReferenceSource;
use App\Repository\WorkerEventReferenceRepository;
use SmartAssert\ResultsClient\Model\ResourceReferenceCollection;
use SmartAssert\ResultsClient\Model\ResourceReferenceCollectionInterface;

class WorkerEventReferenceFactory
{
    public function __construct(
        private readonly ReferenceFactory $referenceFactory,
        private readonly WorkerEventReferenceRepository $repository,
    ) {}

    /**
     * @param non-empty-string $label
     * @param non-empty-string $reference
     */
    public function create(string $label, string $reference): WorkerEventReference
    {
        $workerEventReference = $this->repository->find(WorkerEventReference::generateId($label, $reference));

        if (null === $workerEventReference) {
            $workerEventReference = $this->repository->add(new WorkerEventReference($label, $reference));
        }

        return $workerEventReference;
    }

    /**
     * @param non-empty-string          $jobLabel
     * @param ResourceReferenceSource[] $referenceSources
     */
    public function createCollection(string $jobLabel, array $referenceSources): ResourceReferenceCollectionInterface
    {
        $testReferences = [];
        foreach ($referenceSources as $referenceSource) {
            $reference = $this->referenceFactory->create($jobLabel, $referenceSource->components);
            $testReferences[] = $this->create($referenceSource->label, $reference);
        }

        return new ResourceReferenceCollection($testReferences);
    }
}
