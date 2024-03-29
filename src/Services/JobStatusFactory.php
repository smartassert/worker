<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\Test;
use App\Model\JobStatus;
use App\Model\ResourceReferenceSource;
use App\Repository\SourceRepository;
use App\Repository\TestRepository;
use App\Repository\WorkerEventRepository;

class JobStatusFactory
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly TestRepository $testRepository,
        private readonly ReferenceFactory $referenceFactory,
        private readonly WorkerEventReferenceFactory $resourceReferenceFactory,
        private readonly WorkerEventRepository $workerEventRepository,
    ) {
    }

    public function create(Job $job): JobStatus
    {
        $tests = $this->testRepository->findBy([], ['position' => 'ASC']);

        $testPathReferenceSources = [];
        foreach ($job->testPaths as $testPath) {
            $testPathReferenceSources[] = new ResourceReferenceSource($testPath, [$testPath]);
        }

        return new JobStatus(
            $job,
            $this->referenceFactory->create($job->label),
            $this->sourceRepository->findAllPaths(),
            $this->createSerializedTestCollection($tests),
            $this->resourceReferenceFactory->createCollection($job->label, $testPathReferenceSources),
            $this->workerEventRepository->findAllIds(),
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
                    'browser' => $test->browser,
                    'url' => $test->url,
                    'source' => $test->source,
                    'target' => $test->target,
                    'step_names' => $test->stepNames,
                    'state' => $test->getState()->value,
                    'position' => $test->position,
                ];
            }
        }

        return $serializedTests;
    }
}
