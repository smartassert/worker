<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\Test;
use App\Model\JobStatus;
use App\Model\ResourceReferenceSource;
use App\Repository\SourceRepository;
use App\Repository\TestRepository;

class JobStatusFactory
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly TestRepository $testRepository,
        private readonly CompilationProgress $compilationProgress,
        private readonly ExecutionProgress $executionProgress,
        private readonly EventDeliveryProgress $eventDeliveryProgress,
        private readonly ReferenceFactory $referenceFactory,
        private readonly ResourceReferenceFactory $resourceReferenceFactory,
    ) {
    }

    public function create(Job $job): JobStatus
    {
        $tests = $this->testRepository->findAll();

        $testPathReferenceSources = [];
        foreach ($job->getTestPaths() as $testPath) {
            $testPathReferenceSources[] = new ResourceReferenceSource($testPath, [$testPath]);
        }

        return new JobStatus(
            $job,
            $this->referenceFactory->create($job->getLabel()),
            $this->sourceRepository->findAllPaths(),
            $this->compilationProgress->get(),
            $this->executionProgress->get(),
            $this->eventDeliveryProgress->get(),
            $this->createSerializedTestCollection($tests),
            $this->resourceReferenceFactory->createCollection($job->getLabel(), $testPathReferenceSources)
        );
    }

    /**
     * @param Test[] $tests
     *
     * @return array<int, array<mixed>>
     */
    private function createSerializedTestCollection(array $tests): array
    {
        $serializedTests = [];

        foreach ($tests as $test) {
            if ($test instanceof Test) {
                $serializedTests[] = [
                    'browser' => $test->getBrowser(),
                    'url' => $test->getUrl(),
                    'source' => $test->getSource(),
                    'target' => $test->getTarget(),
                    'step_names' => $test->getStepNames(),
                    'state' => $test->getState()->value,
                    'position' => $test->getPosition(),
                ];
            }
        }

        return $serializedTests;
    }
}
