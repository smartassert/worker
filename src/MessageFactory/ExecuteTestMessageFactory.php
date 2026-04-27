<?php

declare(strict_types=1);

namespace App\MessageFactory;

use App\Exception\JobNotFoundException;
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
        $timeout = $this->defaultCompileTimeoutInSeconds;

        try {
            $job = $this->jobRepository->get();
            $timeout = $job->maximumDurationInSeconds;
        } catch (JobNotFoundException) {
        }

        return new ExecuteTestMessage($testId, $timeout);
    }
}
