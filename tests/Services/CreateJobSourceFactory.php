<?php

declare(strict_types=1);

namespace App\Tests\Services;

use SmartAssert\WorkerJobSource\Factory\JobSourceFactory;
use SmartAssert\WorkerJobSource\JobSourceSerializer;

class CreateJobSourceFactory
{
    public function __construct(
        private readonly YamlProviderFactory $yamlProviderFactory,
        private readonly JobSourceFactory $fooJobSourceFactory,
        private readonly JobSourceSerializer $jobSourceSerializer,
    ) {}

    /**
     * @param non-empty-string[] $manifestPaths
     * @param string[]           $sourcePaths
     */
    public function create(array $manifestPaths, array $sourcePaths): string
    {
        $sourceProvider = $this->yamlProviderFactory->create($sourcePaths);
        $jobSource = $this->fooJobSourceFactory->createFromManifestPathsAndSources($manifestPaths, $sourceProvider);

        return $this->jobSourceSerializer->serialize($jobSource);
    }
}
