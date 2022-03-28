<?php

declare(strict_types=1);

namespace App\Tests\Services;

use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\Collection\Serializer;
use SmartAssert\YamlFile\FileHashes;
use SmartAssert\YamlFile\YamlFile;

class CreateJobSourceFactory
{
    public function __construct(
        private readonly FixtureReader $fixtureReader,
        private readonly Serializer $yamlFileCollectionSerializer,
    ) {
    }

    /**
     * @param string[] $manifestPaths
     * @param string[] $sourcePaths
     */
    public function create(array $manifestPaths, array $sourcePaths): string
    {
        $yamlFiles = [
            YamlFile::create('manifest.yaml', $this->createManifestContent($manifestPaths))
        ];

        $fileHashes = new FileHashes();
        foreach ($sourcePaths as $sourcePath) {
            $content = trim($this->fixtureReader->read($sourcePath));

            $yamlFiles[] = YamlFile::create($sourcePath, $content);
            $fileHashes->add($sourcePath, md5($content));
        }

        $yamlFileCollection = new ArrayCollection($yamlFiles);

        return $this->yamlFileCollectionSerializer->serialize($yamlFileCollection);
    }

    /**
     * @param string[] $manifestPaths
     */
    private function createManifestContent(array $manifestPaths): string
    {
        $lines = [];

        foreach ($manifestPaths as $path) {
            $lines[] = '- ' . $path;
        }

        return implode("\n", $lines);
    }
}
