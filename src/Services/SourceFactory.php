<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Source;
use App\Exception\MissingTestSourceException;
use App\Repository\SourceRepository;
use SmartAssert\WorkerJobSource\Model\JobSource;
use SmartAssert\YamlFile\Exception\ProvisionException;

class SourceFactory
{
    public function __construct(
        private readonly SourceFileStore $sourceFileStore,
        private readonly SourceRepository $sourceRepository,
    ) {}

    /**
     * @throws MissingTestSourceException
     * @throws ProvisionException
     */
    public function createFromJobSource(JobSource $jobSource): void
    {
        $manifestTestPaths = $jobSource->manifest->testPaths;
        $sourcePaths = [];

        foreach ($jobSource->sources->getYamlFiles() as $source) {
            $sourcePath = (string) $source->name;
            $sourcePaths[] = $sourcePath;

            $sourceType = Source::TYPE_RESOURCE;

            if ($jobSource->manifest->contains($sourcePath)) {
                $sourceType = Source::TYPE_TEST;
            }

            $this->sourceFileStore->storeContent($source->content, $sourcePath);
            $this->sourceRepository->add(new Source($sourceType, $sourcePath));
        }

        foreach ($manifestTestPaths as $manifestTestPath) {
            if (false === in_array($manifestTestPath, $sourcePaths)) {
                throw new MissingTestSourceException($manifestTestPath);
            }
        }
    }
}
