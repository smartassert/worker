<?php

declare(strict_types=1);

namespace App\MessageFactory;

use App\Exception\JobNotFoundException;
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
        $timeout = $this->defaultCompileTimeoutInSeconds;

        try {
            $job = $this->jobRepository->get();
            $timeout = $job->maximumDurationInSeconds;
        } catch (JobNotFoundException) {
        }

        return new CompileSourceMessage($path, $timeout);
    }
}
