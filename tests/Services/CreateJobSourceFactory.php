<?php

declare(strict_types=1);

namespace App\Tests\Services;

use SmartAssert\WorkerJobSource\Exception\InvalidManifestException;
use SmartAssert\WorkerJobSource\Factory\JobSourceFactory;
use SmartAssert\WorkerJobSource\JobSourceSerializer;
use SmartAssert\YamlFile\Exception\Collection\SerializeException;

readonly class CreateJobSourceFactory
{
    public function __construct(
        private YamlProviderFactory $yamlProviderFactory,
        private JobSourceFactory $fooJobSourceFactory,
        private JobSourceSerializer $jobSourceSerializer,
    ) {}

    /**
     * @param non-empty-string[] $manifestPaths
     * @param string[]           $sourcePaths
     *
     * @throws InvalidManifestException
     * @throws SerializeException
     */
    public function create(array $manifestPaths, array $sourcePaths): string
    {
        $sourceProvider = $this->yamlProviderFactory->create($manifestPaths, $sourcePaths);
        $jobSource = $this->fooJobSourceFactory->createFromYamlFileCollection($sourceProvider);

        return $this->jobSourceSerializer->serialize($jobSource);
    }
}
