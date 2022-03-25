<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Source;
use App\Exception\MissingTestSourceException;
use App\Model\YamlSourceCollection;
use App\Services\EntityFactory\SourceFactory as SourceEntityFactory;
use SmartAssert\YamlFile\YamlFile;

class SourceFactory
{
    private SourceFileStore $sourceFileStore;
    private SourceEntityFactory $sourceEntityFactory;

    public function __construct(SourceFileStore $sourceFileStore, SourceEntityFactory $sourceEntityFactory)
    {
        $this->sourceFileStore = $sourceFileStore;
        $this->sourceEntityFactory = $sourceEntityFactory;
    }

    /**
     * @throws MissingTestSourceException
     */
    public function createFromYamlSourceCollection(YamlSourceCollection $sourceCollection): void
    {
        $manifest = $sourceCollection->getManifest();
        $manifestTestPaths = $manifest->getTestPaths();
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
            $this->sourceEntityFactory->create($sourceType, $sourcePath);
        }

        foreach ($manifestTestPaths as $manifestTestPath) {
            if (false === in_array($manifestTestPath, $sourcePaths)) {
                throw new MissingTestSourceException($manifestTestPath);
            }
        }
    }
}
