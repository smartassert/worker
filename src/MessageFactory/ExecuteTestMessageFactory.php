<?php

declare(strict_types=1);

namespace App\MessageFactory;

use App\Message\ExecuteTestMessage;
use App\Repository\JobRepository;

readonly class ExecuteTestMessageFactory
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {}

    public function create(int $testId): ?ExecuteTestMessage
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return null;
        }

        return new ExecuteTestMessage($testId, $job->maximumDurationInSeconds);
    }
}
