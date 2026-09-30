<?php

declare(strict_types=1);

namespace App\MessageFactory;

use App\Message\CompileSourceMessage;
use App\Repository\JobRepository;

readonly class CompileSourceMessageFactory
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {}

    /**
     * @param non-empty-string $path
     */
    public function create(string $path): ?CompileSourceMessage
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return null;
        }

        return new CompileSourceMessage($path, $job->maximumDurationInSeconds);
    }
}
