<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Entity\Job;
use App\Services\EntityStore\JobStore;
use Mockery\MockInterface;

class MockJobStore
{
    /**
     * @var JobStore|MockInterface
     */
    private JobStore $jobStore;

    public function __construct()
    {
        $this->jobStore = \Mockery::mock(JobStore::class);
    }

    public function getMock(): JobStore
    {
        return $this->jobStore;
    }

    public function withGetCall(?Job $job): self
    {
        $this->jobStore
            ->shouldReceive('get')
            ->andReturn($job)
        ;

        return $this;
    }
}
