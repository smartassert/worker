<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Source;
use App\Exception\MissingTestSourceException;
use SmartAssert\WorkerJobSource\Model\JobSource;
use SmartAssert\YamlFile\Exception\ProvisionException;

readonly class SourceFactory
{
    public function __construct(
        private SourceFileStore $sourceFileStore,
        private EntityMutator $entityMutator,
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

            $source = new Source($sourceType, $sourcePath);
            $this->entityMutator->save($source);
        }

        foreach ($manifestTestPaths as $manifestTestPath) {
            if (false === in_array($manifestTestPath, $sourcePaths)) {
                throw new MissingTestSourceException($manifestTestPath);
            }
        }
    }
}
