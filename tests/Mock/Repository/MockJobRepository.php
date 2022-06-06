<?php

declare(strict_types=1);

namespace App\Tests\Mock\Repository;

use App\Entity\Job;
use App\Exception\JobNotFoundException;
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

    public function withGetCall(Job|JobNotFoundException $outcome): self
    {
        $expectation = $this->jobStore->shouldReceive('get');

        if ($outcome instanceof Job) {
            $expectation->andReturn($outcome);
        } else {
            $expectation->andThrow($outcome);
        }

        return $this;
    }
}
