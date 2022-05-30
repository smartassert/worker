<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Model\JobStatus;
use App\Repository\SourceRepository;
use App\Repository\TestRepository;

class JobStatusFactory
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly TestRepository $testRepository,
        private readonly TestSerializer $testSerializer,
        private readonly CompilationProgress $compilationProgress,
        private readonly ExecutionProgress $executionProgress,
        private readonly EventDeliveryProgress $eventDeliveryProgress,
        private readonly ReferenceFactory $referenceFactory,
    ) {
    }

    public function create(Job $job): JobStatus
    {
        $tests = $this->testRepository->findAll();

        return new JobStatus(
            $job,
            $this->referenceFactory->create($job->getLabel()),
            $this->sourceRepository->findAllPaths(),
            $this->compilationProgress->get(),
            $this->executionProgress->get(),
            $this->eventDeliveryProgress->get(),
            $this->testSerializer->serializeCollection($tests),
        );
    }
}
