<?php

declare(strict_types=1);

namespace App\Tests\Model;

class EnvironmentSetup
{
    private ?JobSetup $jobSetup = null;

    /**
     * @var SourceSetup[]
     */
    private array $sourceSetups = [];

    /**
     * @var TestSetup[]
     */
    private array $testSetups = [];

    /**
     * @var WorkerEventSetup[]
     */
    private array $workerEventSetups = [];

    public function getJobSetup(): ?JobSetup
    {
        return $this->jobSetup;
    }

    public function withJobSetup(JobSetup $jobSetup): self
    {
        $new = clone $this;
        $new->jobSetup = $jobSetup;

        return $new;
    }

    /**
     * @return SourceSetup[]
     */
    public function getSourceSetups(): array
    {
        return $this->sourceSetups;
    }

    /**
     * @param array<mixed> $sourceSetups
     */
    public function withSourceSetups(array $sourceSetups): self
    {
        $new = clone $this;
        $new->sourceSetups = array_filter($sourceSetups, function ($value) {
            return $value instanceof SourceSetup;
        });

        return $new;
    }

    /**
     * @return TestSetup[]
     */
    public function getTestSetups(): array
    {
        return $this->testSetups;
    }

    /**
     * @param array<mixed> $testSetups
     */
    public function withTestSetups(array $testSetups): self
    {
        $new = clone $this;
        $new->testSetups = array_filter($testSetups, function ($value) {
            return $value instanceof TestSetup;
        });

        return $new;
    }

    /**
     * @return WorkerEventSetup[]
     */
    public function getWorkerEventSetups(): array
    {
        return $this->workerEventSetups;
    }

    /**
     * @param WorkerEventSetup[] $workerEventSetups
     */
    public function withWorkerEventSetups(array $workerEventSetups): self
    {
        $new = clone $this;
        $new->workerEventSetups = $workerEventSetups;

        return $new;
    }
}
