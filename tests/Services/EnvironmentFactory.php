<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Repository\JobRepository;
use App\Repository\SourceRepository;
use App\Tests\Model\Environment;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;

class EnvironmentFactory
{
    public function __construct(
        private JobRepository $jobRepository,
        private SourceRepository $sourceRepository,
        private TestTestFactory $testTestFactory,
        private TestWorkerEventFactory $testWorkerEventFactory,
    ) {
    }

    public function create(EnvironmentSetup $setup): Environment
    {
        $environment = new Environment();

        $jobSetup = $setup->getJobSetup();
        if ($jobSetup instanceof JobSetup) {
            $job = $this->jobRepository->add(new Job(
                $jobSetup->getLabel(),
                $jobSetup->getEventDeliveryUrl(),
                $jobSetup->getMaximumDurationInSeconds(),
                $jobSetup->getTestPaths(),
            ));

            $environment = $environment->withJob($job);
        }

        $sources = [];
        foreach ($setup->getSourceSetups() as $sourceSetup) {
            $sources[] = $this->sourceRepository->add(
                Source::create($sourceSetup->getType(), $sourceSetup->getPath())
            );
        }

        $tests = [];
        foreach ($setup->getTestSetups() as $testSetup) {
            $tests[] = $this->testTestFactory->create($testSetup);
        }

        $workerEvents = [];
        foreach ($setup->getWorkerEventSetups() as $workerEventSetup) {
            $workerEvents[] = $this->testWorkerEventFactory->create($workerEventSetup);
        }

        return $environment
            ->withSources($sources)
            ->withTests($tests)
            ->withWorkerEvents($workerEvents)
        ;
    }
}
