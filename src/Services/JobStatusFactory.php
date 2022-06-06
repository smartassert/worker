<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\JobNotFoundException;
use App\Model\JobStatus;
use App\Repository\JobRepository;
use App\Repository\SourceRepository;
use App\Repository\TestRepository;

class JobStatusFactory
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly SourceRepository $sourceRepository,
        private readonly TestRepository $testRepository,
        private readonly TestSerializer $testSerializer,
        private readonly CompilationProgress $compilationProgress,
        private readonly ExecutionProgress $executionProgress,
        private readonly EventDeliveryProgress $eventDeliveryProgress,
        private readonly ReferenceFactory $referenceFactory,
        private readonly ResourceReferenceFactory $resourceReferenceFactory,
    ) {
    }

    /**
     * @throws JobNotFoundException
     */
    public function create(): JobStatus
    {
        $job = $this->jobRepository->get();
        $tests = $this->testRepository->findAll();

        return new JobStatus(
            $job,
            $this->referenceFactory->create(),
            $this->sourceRepository->findAllPaths(),
            $this->compilationProgress->get(),
            $this->executionProgress->get(),
            $this->eventDeliveryProgress->get(),
            $this->testSerializer->serializeCollection($tests),
            $this->resourceReferenceFactory->createCollection($job->getTestPaths())
        );
    }
}
