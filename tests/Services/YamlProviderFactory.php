<?php

declare(strict_types=1);

namespace App\Tests\Services;

use SmartAssert\WorkerJobSource\Model\Manifest;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\Collection\ProviderInterface;
use SmartAssert\YamlFile\FileHashes;
use SmartAssert\YamlFile\Filename;
use SmartAssert\YamlFile\YamlFile;

readonly class YamlProviderFactory
{
    public function __construct(
        private FixtureReader $fixtureReader,
    ) {}

    /**
     * @param non-empty-string[] $manifestPaths
     * @param string[]           $sourcePaths
     */
    public function create(array $manifestPaths, array $sourcePaths): ProviderInterface
    {
        $manifestContent = '';
        foreach ($manifestPaths as $manifestPath) {
            $manifestContent .= '- ' . $manifestPath . "\n";
        }
        $manifestContent = rtrim($manifestContent);

        $yamlFiles = [];
        $yamlFiles[] = new YamlFile(Filename::parse(Manifest::FILENAME), $manifestContent);

        $fileHashes = new FileHashes();
        foreach ($sourcePaths as $sourcePath) {
            $content = trim($this->fixtureReader->read($sourcePath));

            $yamlFiles[] = YamlFile::create($sourcePath, $content);
            $fileHashes->add($sourcePath, md5($content));
        }

        return new ArrayCollection($yamlFiles);
    }
}
