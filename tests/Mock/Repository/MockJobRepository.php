<?php

declare(strict_types=1);

namespace App\Tests\Mock\Repository;

use App\Entity\Job;
use App\Repository\JobRepository;
use Mockery\MockInterface;

class MockJobRepository
{
    /**
     * @var JobRepository|MockInterface
     */
    private JobRepository $jobStore;

    public function __construct()
    {
        $this->jobStore = \Mockery::mock(JobRepository::class);
    }

    public function getMock(): JobRepository
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
