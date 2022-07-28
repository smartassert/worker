<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Source;
use App\Exception\MissingTestSourceException;
use App\Model\YamlSourceCollection;
use App\Repository\SourceRepository;
use SmartAssert\YamlFile\YamlFile;

class SourceFactory
{
    public function __construct(
        private readonly SourceFileStore $sourceFileStore,
        private readonly SourceRepository $sourceRepository,
    ) {
    }

    /**
     * @throws MissingTestSourceException
     */
    public function createFromYamlSourceCollection(YamlSourceCollection $sourceCollection): void
    {
        $manifest = $sourceCollection->getManifest();
        $manifestTestPaths = $manifest->testPaths;
        $sourcePaths = [];

        $sources = $sourceCollection->getYamlFiles();

        /** @var YamlFile $source */
        foreach ($sources->getYamlFiles() as $source) {
            $sourcePath = (string) $source->name;
            $sourcePaths[] = $sourcePath;

            $sourceType = Source::TYPE_RESOURCE;

            if ($manifest->isTestPath($sourcePath)) {
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
