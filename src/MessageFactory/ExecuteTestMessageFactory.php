<?php

declare(strict_types=1);

namespace App\MessageFactory;

use App\Entity\Job;
use App\Message\ExecuteTestMessage;
use App\Repository\JobRepository;

readonly class ExecuteTestMessageFactory
{
    public function __construct(
        private JobRepository $jobRepository,
        private int $defaultCompileTimeoutInSeconds,
    ) {}

    public function create(int $testId): ExecuteTestMessage
    {
        $job = $this->jobRepository->get();

        $timeout = $job instanceof Job
            ? $job->maximumDurationInSeconds
            : $this->defaultCompileTimeoutInSeconds;

        return new ExecuteTestMessage($testId, $timeout);
    }
}
