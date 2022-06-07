<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\JobRepository;

class ReferenceFactory
{
    public function __construct(
        private readonly JobRepository $jobRepository,
    ) {
    }

    /**
     * @param string[] $components
     *
     * @return non-empty-string
     */
    public function create(array $components = []): string
    {
        array_unshift($components, (string) $this->jobRepository->getLabel());

        return md5(implode('', $components));
    }
}
