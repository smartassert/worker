<?php

declare(strict_types=1);

namespace App\MessageFactory;

use App\Entity\Job;
use App\Message\CompileSourceMessage;
use App\Repository\JobRepository;

readonly class CompileSourceMessageFactory
{
    public function __construct(
        private JobRepository $jobRepository,
        private int $defaultCompileTimeoutInSeconds,
    ) {}

    /**
     * @param non-empty-string $path
     */
    public function create(string $path): CompileSourceMessage
    {
        $job = $this->jobRepository->get();

        $timeout = $job instanceof Job
            ? $job->maximumDurationInSeconds
            : $this->defaultCompileTimeoutInSeconds;

        return new CompileSourceMessage($path, $timeout);
    }
}
