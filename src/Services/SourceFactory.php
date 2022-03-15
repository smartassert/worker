<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Source;
use App\Exception\MissingTestSourceException;
use App\Model\Manifest;
use App\Model\UploadedSource;
use App\Model\UploadedSourceCollection;
use App\Model\YamlSourceCollection;
use App\Services\EntityFactory\SourceFactory as SourceEntityFactory;
use SmartAssert\YamlFile\Exception\ProvisionException;
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
    public function createCollectionFromManifest(Manifest $manifest, UploadedSourceCollection $uploadedSources): void
    {
        $manifestTestPaths = $manifest->getTestPaths();

        foreach ($manifestTestPaths as $manifestTestPath) {
            if (false === $uploadedSources->contains($manifestTestPath)) {
                throw new MissingTestSourceException($manifestTestPath);
            }

            $uploadedSource = $uploadedSources[$manifestTestPath];
            if (!$uploadedSource instanceof UploadedSource) {
                throw new MissingTestSourceException($manifestTestPath);
            }
        }

        foreach ($uploadedSources as $uploadedSource) {
            /** @var UploadedSource $uploadedSource */
            $uploadedSourceRelativePath = $uploadedSource->getPath();
            $sourceType = Source::TYPE_RESOURCE;

            if ($manifest->isTestPath($uploadedSourceRelativePath)) {
                $sourceType = Source::TYPE_TEST;
            }

            $this->sourceFileStore->store($uploadedSource, $uploadedSourceRelativePath);

            $this->sourceEntityFactory->create($sourceType, $uploadedSourceRelativePath);
        }
    }

    /**
     * @throws MissingTestSourceException
     * @throws ProvisionException
     */
    public function createFromYamlSourceCollection(YamlSourceCollection $sourceCollection): void
    {
        $manifest = $sourceCollection->getManifest();
        $manifestTestPaths = $manifest->getTestPaths();
        $sourceTestPaths = [];

        $sources = $sourceCollection->getYamlFiles();

        /** @var YamlFile $source */
        foreach ($sources->getYamlFiles() as $source) {
            $sourceTestPaths[] = (string) $source->name;
        }

        foreach ($manifestTestPaths as $manifestTestPath) {
            if (false === in_array($manifestTestPath, $sourceTestPaths)) {
                throw new MissingTestSourceException($manifestTestPath);
            }
        }

        /** @var YamlFile $source */
        foreach ($sources->getYamlFiles() as $source) {
            $sourcePath = (string) $source->name;

            $sourceType = Source::TYPE_RESOURCE;

            if ($manifest->isTestPath($sourcePath)) {
                $sourceType = Source::TYPE_TEST;
            }

            $this->sourceFileStore->storeContent($source->content, $sourcePath);
            $this->sourceEntityFactory->create($sourceType, $sourcePath);
        }
    }
}
