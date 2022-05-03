<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Repository\JobRepository;
use App\Services\EntityFactory\SourceFactory;
use App\Tests\Model\Environment;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;

class EnvironmentFactory
{
    public function __construct(
        private JobRepository $jobRepository,
        private SourceFactory $sourceFactory,
        private TestTestFactory $testTestFactory,
        private TestCallbackFactory $testCallbackFactory,
    ) {
    }

    public function create(EnvironmentSetup $setup): Environment
    {
        $environment = new Environment();

        $jobSetup = $setup->getJobSetup();
        if ($jobSetup instanceof JobSetup) {
            $job = $this->jobRepository->create(
                $jobSetup->getLabel(),
                $jobSetup->getCallbackUrl(),
                $jobSetup->getMaximumDurationInSeconds()
            );

            $environment = $environment->withJob($job);
        }

        $sources = [];
        foreach ($setup->getSourceSetups() as $sourceSetup) {
            $sources[] = $this->sourceFactory->create($sourceSetup->getType(), $sourceSetup->getPath());
        }

        $tests = [];
        foreach ($setup->getTestSetups() as $testSetup) {
            $tests[] = $this->testTestFactory->create($testSetup);
        }

        $callbacks = [];
        foreach ($setup->getCallbackSetups() as $callbackSetup) {
            $callbacks[] = $this->testCallbackFactory->create($callbackSetup);
        }

        return $environment
            ->withSources($sources)
            ->withTests($tests)
            ->withCallbacks($callbacks)
        ;
    }
}
